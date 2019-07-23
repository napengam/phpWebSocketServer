
# NOTE


Use at own risc. 

## adressPort.inc.php

adress of host and port to connect to the server

<b>NOTE:</b>

Specify the host adress like.
<ul>
<li> ssl://xyzabc.worldserver.net   
<li> tcp://xyzabc.worldserver.net
</ul>  

## socketPhpClient.php

class to connect to the server 

## testWithPHPSockets.php

Have the server started and waiting ....

> php testWithPHPSockets.php

in the command window  where you startet the server you should see some output
like this 
<pre>
[Tue, 20 Nov 18 12:36:12 +0100] - New client connecting on socket #7
[Tue, 20 Nov 18 12:36:12 +0100] - Handshake:php process


[Tue, 20 Nov 18 12:36:12 +0100] - Telling Client to start on  #8
[Tue, 20 Nov 18 12:36:12 +0100] - Received bytes = 50
[Tue, 20 Nov 18 12:36:12 +0100] - Broadcast {"opcode":"broadcast","message1":"hallo from PHP"}
[Tue, 20 Nov 18 12:36:12 +0100] - Received bytes = 50
[Tue, 20 Nov 18 12:36:12 +0100] - Broadcast {"opcode":"broadcast","message2":"hallo from PHP"}
[Tue, 20 Nov 18 12:36:12 +0100] - Received bytes = 50
[Tue, 20 Nov 18 12:36:12 +0100] - Broadcast {"opcode":"broadcast","message3":"hallo from PHP"}
[Tue, 20 Nov 18 12:36:12 +0100] - Received bytes = 50
[Tue, 20 Nov 18 12:36:12 +0100] - Broadcast {"opcode":"broadcast","message4":"hallo from PHP"}
[Tue, 20 Nov 18 12:36:12 +0100] - Received bytes = 50
[Tue, 20 Nov 18 12:36:12 +0100] - Broadcast {"opcode":"broadcast","message5":"hallo from PHP"}
[Tue, 20 Nov 18 12:36:12 +0100] - Received bytes = 17
[Tue, 20 Nov 18 12:36:12 +0100] - QUIT; Connection closed to socket #8
[Tue, 20 Nov 18 12:36:12 +0100] - Connection closed to socket #8
</pre>