<?php

class getOptions {
    public $default = [];
    function __construct() {
        $in = $this->getOptArgv(['-i', '-logfile', '-adress', '-console']);

        if (isset($in['i'])) {
            $ini = parse_ini_file($in['i'], false, INI_SCANNER_TYPED);
        } else {
            $ini = parse_ini_file('websock.ini', false, INI_SCANNER_TYPED);
        }
        if ($ini === false) {
            openlog('websock', LOG_PID, LOG_USER);
            syslog(LOG_ERR, "no ini file found or not specified");
            closelog();
            exit;
        }
        $this->default = $this->overwriteAdd($ini, $in);
        return (object) $this->default;
    }

    private function overwriteAdd($default, $param) {
        foreach ($param as $key => $value) {
            $default[$key] = $value;
        }
        return $default;
    }

    function getOptArgv($expect) {
        global $argv, $argc;
        $out = [];
        for ($i = 1; $i < $argc; $i++) {
            if (array_search($argv[$i], $expect)) {
                $exp = mb_substr($argv[$i], 1);             
                if ($i + 1 < $argc) {
                    if (mb_substr($argv[$i + 1], 0, 1) !== '-') {
                        $i++;
                        $out[$exp] = $argv[$i]; //parameter is given with value
                    } else {
                        $out[$exp] = '1';  // parameter is given with no value
                    }
                } else {
                    $out[$exp] = '1'; // parameter is given with no value
                }
            }
        }
        return $out;
    }

}
