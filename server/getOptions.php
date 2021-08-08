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
            foreach ($expect as $exp) {
                if ($exp == $argv[$i]) {
                    if ($i + 1 < $argc) {
                        $i++;
                        $out[mb_substr($exp, 1)] = $argv[$i];
                    }
                }
            }
        }
        return $out;
    }

}
$x = new getOptions();
$o = $x->default;
