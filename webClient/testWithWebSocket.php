<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>WebClient</title>
    </head>
    <body>
        <b>Status: <span id='connect' style="font-size:1.2em">not connected</span></b><p>
            <button style="display:inline-block" id="open" >Connect to Server</button>
            <button id="close" >Close connection</button><p>

        <div style="border:1px solid black;display:inline-block">
            <button id="ajax" >CALL Backend via AJAX</button>  Here you will see feedback from backend : <b><span id='feedback'></span> </b>
        </div>
        <div style="border:1px solid black;display:inline-block">
            <button id="echo" >Echo message</button>  Here you will see echo : <b><span id='echomsg'></span> </b>
        </div>
        <hr>
        <button id="ready" >Talk to others; my UUID=<b><span id='uuid'></span></b> </button>
        <div id="broadcast">
            <b>Messages from other web clients:</b><br>
        </div>
        <?php
        include '../include/adressPort.inc.php';
        /*
         * ***********************************************
         * globals for the client module socketWebClient.js
         * ***********************************************
         */
        echo "<script>"
        . "server='$Address';"
        . "port='$Port';"
        . "</script>";
        ?>
        <script src="socketWebClient.js"></script>
        <script src="startWebClient.js"></script>
    </body>
</html>
