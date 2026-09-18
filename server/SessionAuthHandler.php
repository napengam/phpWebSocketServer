<?php

class SessionAuthHandler {

    public static function authenticateClient(string $buffer): bool {


        $req = GetAllConfig::get('jsauth.require', false);
        if (!$req) {
            return true; // WARNING ALL WEBSOCKET CLIENTS CAN CONNECT !!!
        }
        $cookieName = GetAllConfig::get('jsauth.cookie_name', 'PHPSESSID');
        $ssp = GetAllConfig::get('jsauth.ssp', '');
        $userKey = GetAllConfig::get('jsauth.key', '');
        $pattern = '/(?:Cookie:)?.*?(?:\b|;\s*)' . preg_quote($cookieName, '/') . '=([^;\r\n\s]+)/i';
        if (!preg_match($pattern, $buffer, $m)) {
            return false;
        }

        $file = rtrim($ssp, '/\\') . '/sess_' . $m[1];
        if (!is_file($file)) {
            return false;
        }

        $raw = file_get_contents($file);
        if (!$raw) {
            return false;
        }

        return self::lookForKey($raw, $userKey);
    }

    private static function lookForKey(string $raw, ?string $userKey = null): bool {
        if ($userKey === null) {
            return false;
        }
        $offset = 0;
        while (($pipe = strpos($raw, '|', $offset)) !== false) {
            $key = substr($raw, $offset, $pipe - $offset);
            $offset = $pipe + 1;
            $val = @unserialize(substr($raw, $offset));
            if ($val === false && substr($raw, $offset, 4) !== 'b:0;') {
                break;
            }
            if ($key === $userKey) {
                return true;
            }
            $offset += strlen(serialize($val));
        }
        return false;
    }
}
