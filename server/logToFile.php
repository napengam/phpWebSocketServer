<?php

/**
 * Description of logFileCore
 *
 * @author Heinz
 */
class logToFile {

    public $logFile, $error = '', $fh = '', $console;
    private $logDir, $maxEntry = 100000, $numLinesNow, $logOnOff, $pid, $logFileOrg;

    function __construct($logDirFile, $ident, $message = '', $console = false) {
        $logDir = dirname($logDirFile);

        $this->logOnOff = true;
        $this->console = $console;
        $this->ident = $ident;
        $this->logFileOrg = $logDirFile;

        if ($logDir == '') {
            $logDir = getcwd();
        }
        $this->logDir = $logDir;

        if (!is_dir($logDir)) {
            $this->error = "$logDir is not a directory";
        }
        if (!is_writeable($logDir)) {
            $this->error = "$logDir is not writable";
        }
        $this->pid = getmypid();
        if ($this->error) {
            openlog($ident, LOG_PID, LOG_USER);
            syslog(LOG_ERR, "can not access LOGDIR $logDirFile; no loging");
            closelog();
            $this->logOnOff = false;
        }
        $this->logOpen($logDirFile);
        if ($message) {
            $this->log($message);
        }
    }

    function logOpen($logDirFile) {
        if ($this->logOnOff === false || $logDirFile == '') {
            return;
        }
        $num = 1;
        $fp = (object) pathinfo($logDirFile);

        if ($fp->extension) {
            $dot = ".";
        } else {
            $dot = '';
            $fp->extension = '';
        }
        $this->fh = fopen($logDirFile, 'a+');

        if ($this->numLines($logDirFile) > $this->maxEntry) {
            $num = $this->logNum($fp->dirname . "/" . $fp->filename, $dot, $fp->extension);
            fclose($this->fh);
            rename($logDirFile, "$fp->dirname/$fp->filename$num$dot$fp->extension");
        }
        $this->fh = fopen($logDirFile, 'a+');
        $this->numLinesNow++;
        if ($this->fh === false) {
            openlog($this->ident, LOG_PID, LOG_USER);
            syslog(LOG_ERR, "can not open $logDirFile; no loging");
            closelog();
            $this->logOnOff = false;
        }
    }

    function log($m) {
        if ($this->console) {
            echo date('r') . ";" . $m . "\r\n";
        }
        if ($this->logOnOff === false) {
            return;
        }
        if ($this->fh) {
            fputs($this->fh, date('r') . ";" . $m . "\r\n");
            $this->numLinesNow++;
            if ($this->numLinesNow > $this->maxEntry) {
                $this->logClose();
                $this->logOpen($this->logFileOrg);
                $this->numLinesNow = 0;
            }
        }
    }

    function logClose() {
        if ($this->fh) {
            fclose($this->fh);
        }
    }

    function logMode($onOff) {
        if ($this->error === '') {
            $this->logOnOff = $onOff;
        }
    }

    private function logNum($filename, $dot, $extension) {
        $max = 0;
        $out = [];
        foreach (glob("$filename*$dot$extension") as $fn) {
            preg_match_all('/[0-9]*/', $fn, $out);
            $n = trim(implode('', $out[0]));
            if ($n > $max) {
                $max = (int) $n;
            }
        }
        return $max + 1;
    }

    private function numLines($file) {
        $f = fopen($file, 'rb');
        $lines = 0;
        while (!feof($f)) {
            $lines += substr_count(fread($f, 8192), "\n");
        }
        fclose($f);
        $this->numLinesNow = $lines;
        return $lines;
    }

}
