<?php

class GetOptionsx {

    private array $config = [];

    public function __construct(
        string $projectRootName,
        string $iniName,
        array $expected  = ['-i', '-logfile', '-address', '-console'],
        array $boolFlags = ['-console'],
        ?string $startDir = null,
        ?array $argv = null
    ) {
        if ($iniName === '') {
            throw new InvalidArgumentException('iniName must not be empty');
        }

        if (!in_array('-i', $expected, true)) {
            $expected[] = '-i';
        }

        $startDir    = $startDir ?? $this->detectStartDir();
        $projectRoot = $this->findProjectRoot($projectRootName, $startDir);
        $configDir   = $projectRoot . '/config';

        $argv = $argv ?? ($_SERVER['argv'] ?? []);
        $cliOptions = $this->parseArgv($argv, $expected, $boolFlags);

        $iniArg  = $cliOptions['i'] ?? $iniName;
        $iniFile = $this->resolveIniPath($iniArg, $configDir);

        if (!file_exists($iniFile)) {
            throw new RuntimeException("Config file not found: $iniFile");
        }

        $ini = parse_ini_file($iniFile, false, INI_SCANNER_TYPED);
        if ($ini === false) {
            throw new RuntimeException("Failed to parse ini file: $iniFile");
        }

        $this->config = array_replace($ini, $cliOptions);
        $this->config['_projectRoot'] = $projectRoot;
        $this->config['_configDir']   = $configDir;
        $this->config['_iniFile']     = $iniFile;
    }

    public function getConfig(): array {
        return $this->config;
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->config[$key] ?? $default;
    }

    private function detectStartDir(): string {
        if (!empty($_SERVER['SCRIPT_FILENAME'])) {
            return dirname(realpath($_SERVER['SCRIPT_FILENAME']) ?: $_SERVER['SCRIPT_FILENAME']);
        }
        if (!empty($_SERVER['argv'][0])) {
            $script = $_SERVER['argv'][0];
            return dirname(realpath($script) ?: $script);
        }
        return getcwd() ?: __DIR__;
    }

    private function findProjectRoot(string $name, string $startDir): string {
        $dir = $startDir;

        for ($i = 0; $i < 20; $i++) {
            if (basename($dir) === $name) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        throw new RuntimeException(
            "Project root '$name' not found above: $startDir"
        );
    }

    /**
     * - absoluter Pfad           -> unverändert
     * - reiner Dateiname         -> $configDir/<name>
     * - relativer Pfad mit Slash -> $configDir/<path>
     */
    private function resolveIniPath(string $path, string $configDir): string {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }
        return $configDir . '/' . ltrim($path, '/\\');
    }

    private function isAbsolutePath(string $path): bool {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }
        if (strlen($path) >= 3 && ctype_alpha($path[0]) && $path[1] === ':') {
            return true;
        }
        return false;
    }

    private function parseArgv(array $argv, array $expect, array $boolFlags): array {
        $out  = [];
        $argc = count($argv);

        for ($i = 1; $i < $argc; $i++) {
            $arg = $argv[$i];

            if (!in_array($arg, $expect, true)) {
                fwrite(STDERR, "Unknown option ignored: $arg\n");
                continue;
            }

            $key = ltrim($arg, '-');

            if (in_array($arg, $boolFlags, true)) {
                $out[$key] = true;
                continue;
            }

            if ($i + 1 >= $argc) {
                throw new RuntimeException("Missing value for option: $arg");
            }

            $next = $argv[$i + 1];
            if ($next === '' || str_starts_with($next, '-')) {
                throw new RuntimeException("Missing value for option: $arg");
            }

            $i++;
            $out[$key] = $next;
        }

        return $out;
    }
}
