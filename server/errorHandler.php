<?php

set_error_handler('errorException'); 

if (function_exists('errorException')) {    
    return true;
}

function errorException($errno, $errstr, $errfile, $errline) {
    global $logger;
  
    if (!(error_reporting() & $errno)) {
        return true;
    }
    $logger->log("errno: $errno ; $errstr in file $errfile , Line: $errline");
    return true;
}


