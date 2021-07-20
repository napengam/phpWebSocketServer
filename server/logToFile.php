<?php

/**
 * Description of logFileCore
 *
 * @author Heinz
 */
class logToFile {

    public $logFile, $error = '', $fh = '', $console;
    private $logDir, $maxEntry = 100000, $numLinesNow, $logOnOff;

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
    }

    function logOpen($logFile, $option = 'a') {
        $this->logFile = $logFile;
        if ($logFile == '') {
            return;
        }
        if (file_exists("$this->logDir/$logFile")) {
            if ($this->numLines("$this->logDir/$logFile") > $this->maxEntry) {
                $option = 's';
            }
        }
        if ($option == 's') { // save logfile if exsists
            if (file_exists("$this->logDir/$logFile")) {
                rename("$this->logDir/$logFile", "$this->logDir/$logFile-" . time());
            }
            $this->fh = fopen("$this->logDir/$logFile", 'w+');
        } else if ($option == 'a') {// append to logfile
            $this->fh = fopen("$this->logDir/$logFile", 'a+');
            $this->numLinesNow++;
        }
    }

    function log($m) {
        if ($this->logOnOff === false) {
            return;
        }
        if ($this->fh) {
            fputs($this->fh, date('r') . "; " . $m . "\r\n");
            $this->numLinesNow++;
            if ($this->numLinesNow > $this->maxEntry) {
                $this->logClose();
                $this->logOpen($this->logFile);
                $this->numLinesNow = 0;
            }
        }
        if ($this->console) {
            echo date('r') . "; " . $m . "\r\n";
        }
    }

    function logClose() {
        if ($this->fh) {
            fclose($this->fh);
        }
    }

    function logMode($onOff) {
        $this->logOnOff = $onOff;
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
