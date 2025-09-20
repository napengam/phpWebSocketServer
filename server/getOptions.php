<?php

class GetOptions {

    private array $config = [];

    public function __construct(array $expected = ['-i', '-logfile', '-address', '-console'], string $defaultIni = 'ini.ini') {
        $cliOptions = $this->getOptArgv($expected);

        $iniFile = $cliOptions['i'] ?? $defaultIni;
        if (!file_exists($iniFile)) {
            throw new RuntimeException("Config file not found: $iniFile");
        }
        $ini = parse_ini_file($iniFile, false, INI_SCANNER_TYPED);

        if ($ini === false) {
            throw new RuntimeException("Failed to parse ini file: $iniFile");
        }

        // Merge CLI overrides on top of ini config
        $this->config = array_replace($ini, $cliOptions);
    }

    public function getConfig(): array {
        return $this->config;
    }

    private function getOptArgv(array $expect): array {
        global $argv, $argc;
        $out = [];

        for ($i = 1; $i < $argc; $i++) {
            if (!in_array($argv[$i], $expect, true)) {
                continue;
            }

            $exp = ltrim($argv[$i], '-');

            if ($i + 1 < $argc && $argv[$i + 1][0] !== '-') {
                $i++;
                $out[$exp] = $argv[$i];
            } else {
                $out[$exp] = true; // boolean flag
            }
        }

        return $out;
    }
}
