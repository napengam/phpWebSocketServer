<?php

class SessionAuthHandler {

    private static ?array $config = null;
    private static array $sessionCache = [];
    private static int $sessionCacheLimit = 1000;

    public static function authenticateClient(string $cookie): bool {

        $config = self::getConfig();

        if (!$config['required']) {
            return true; // WARNING ALL WEBSOCKET CLIENTS CAN CONNECT !!!
        }
        if ($cookie === '') {
            return false;  // can not continue
        }
        if ($config['ssp'] === '' || $config['key'] === '') {
            return false;
        }

        $pattern = '/(?:^|;\s*|Cookie:\s*)' .
                preg_quote($config['cookie_name'], '/') .
                '=([^;\r\n\s]+)/i';
        $matches = [];
        if (!preg_match($pattern, $cookie, $matches)) {
            return false;
        }

        $sessionId = $matches[1];

        if (!preg_match('/^[a-zA-Z0-9,-]+$/', $sessionId)) {
            return false;
        }

        $file = $config['ssp'] . '/sess_' . $sessionId;

        if (!is_file($file)) {
            unset(self::$sessionCache[$file]);
            return false;
        }

        $stat = @stat($file);

        if ($stat === false) {
            return false;
        }

        $mtime = (int) ($stat['mtime'] ?? 0);
        $size = (int) ($stat['size'] ?? 0);

        if (isset(self::$sessionCache[$file])) {
            $cached = self::$sessionCache[$file];

            if ($cached['mtime'] === $mtime && $cached['size'] === $size) {
                return $cached['valid'];
            }
        }

        $raw = @file_get_contents($file);

        if ($raw === false || $raw === '') {
            self::cacheResult($file, $mtime, $size, false);
            return false;
        }

        $valid = self::lookForKey($raw, $config['key']);

        self::cacheResult($file, $mtime, $size, $valid);

        return $valid;
    }

    private static function getConfig(): array {
        if (self::$config !== null) {
            return self::$config;
        }

        $config = GetAllConfig::load()['jsauth'] ?? [];

        self::$config = [
            'required' => (bool) ($config['require'] ?? false),
            'cookie_name' => (string) ($config['cookie_name'] ?? 'PHPSESSID'),
            'ssp' => rtrim((string) ($config['ssp'] ?? ''), '/\\'),
            'key' => (string) ($config['key'] ?? ''),
        ];

        return self::$config;
    }

    private static function cacheResult(
            string $file,
            int $mtime,
            int $size,
            bool $valid
    ): void {
        if (count(self::$sessionCache) >= self::$sessionCacheLimit) {
            array_shift(self::$sessionCache);
        }

        self::$sessionCache[$file] = [
            'mtime' => $mtime,
            'size' => $size,
            'valid' => $valid,
        ];
    }

    private static function lookForKey(string $raw, ?string $userKey = null): bool {
        if ($userKey === null || $userKey === '') {
            return false;
        }

        $offset = 0;
        $length = strlen($raw);

        while ($offset < $length && ($pipe = strpos($raw, '|', $offset)) !== false) {
            $key = substr($raw, $offset, $pipe - $offset);
            $offset = $pipe + 1;

            if ($key === $userKey) {
                return true;
            }

            $serialized = substr($raw, $offset);
            $value = @unserialize($serialized, [
                        'allowed_classes' => false,
            ]);

            if ($value === false && substr($serialized, 0, 4) !== 'b:0;') {
                return false;
            }

            $offset += strlen(serialize($value));
        }

        return false;
    }

    public static function clearSessionCache(): void {
        self::$sessionCache = [];
    }

    public static function clearConfigCache(): void {
        self::$config = null;
    }
}
