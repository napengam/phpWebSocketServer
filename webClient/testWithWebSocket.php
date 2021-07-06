<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>WebClient</title>
    </head>
    <body>
        <button id="open" >Connect to Server</button><br>
        <button id="close" >Close connection</button><br>
        Status:<b><span id='connect'>not connected</span></b><br>
        <button id="ajax" >CALL Backend via AJAX</button><br>
        Here you will see feedback from backend : <b><span id='feedback'></span> </b>
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
