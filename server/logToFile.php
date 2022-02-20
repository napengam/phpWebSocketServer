<?php

/**
 * Description of logFileCore
 *
 * @author Heinz
 */
class logToFile {

    public $logFile, $error = '', $fh = '', $console;
    private $logDir, $maxEntry = 100000, $numLinesNow, $logOnOff, $pid, $logFileOrg;

    function __construct($logDir, $console = false) {
        $this->logOnOff = true;
        $this->console = $console;
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
            openlog('websock', LOG_PID, LOG_USER);
            syslog(LOG_ERR, "can not access LOGDIR $logDir; no loging");
            closelog();
            $this->logOnOff = false;
        }
    }

    function logOpen($logFile, $option = 'a') {
        if ($this->logOnOff === false) {
            return;
        }
        $this->logFile = $this->pid . "-" . $logFile;
        if ($logFile == '') {
            return;
        }
        $this->logFileOrg = $logFile;
        $logFile = $this->pid . "-" . $logFile;
        if (file_exists("$this->logDir/$logFile")) {
            if ($this->numLines("$this->logDir/$logFile") > $this->maxEntry) {
                $option = 's';
            }
        }
        if ($option == 's') { // save logfile if exsists
            if (file_exists("$this->logDir/$logFile")) {
                if (file_exists("$this->logDir/$this->logFileOrg-$this->pid")) {
                    unlink("$this->logDir/$this->logFileOrg-$this->pid");
                }
                rename("$this->logDir/$logFile", "$this->logDir/$this->logFileOrg-$this->pid");
            }
            $this->fh = fopen("$this->logDir/$logFile", 'w+');
        } else if ($option == 'a') {// append to logfile
            $this->fh = fopen("$this->logDir/$logFile", 'a+');
            $this->numLinesNow++;
        }
        if ($this->fh === false) {
            openlog('websock', LOG_PID, LOG_USER);
            syslog(LOG_ERR, "can not open $this->logDir/$logFile; no loging");
            closelog();
            $this->logOnOff = false;
        }
    }

    function log($m) {
        if ($this->console) {
            echo date('r') . "; " . $m . "\r\n";
        }
        if ($this->logOnOff === false) {
            return;
        }
        if ($this->fh) {
            fputs($this->fh, date('r') . "; " . $m . "\r\n");
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
