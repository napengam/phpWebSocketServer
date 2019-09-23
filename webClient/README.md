
# NOTE


Use at own risc. 


## socketWebClient.js

java script to connect to the server 

## testWithWebSockets.php

Have the server started and waiting ....

### Step 1

In a browser window(1) run an URL adressing  testWithPHPSockets.php
in the command window  where you startet the server you should see some output
like this 

<pre>
Connection: Upgrade
Pragma: no-cache
Cache-Control: no-cache
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.67 Safari/537.36
Upgrade: websocket
Origin: http://localhost
Sec-WebSocket-Version: 13
Accept-Encoding: gzip, deflate, br
Accept-Language: en,de-DE;q=0.9,de;q=0.8,en-US;q=0.7
Sec-WebSocket-Key: R7nEptzBbU+85Mn+GLIuTQ==
Sec-WebSocket-Extensions: permessage-deflate; client_max_window_bits


[Wed, 21 Nov 18 10:53:08 +0100] - Telling Client to start on  #12
[Wed, 21 Nov 18 10:53:08 +0100] - Received bytes = 72
[Wed, 21 Nov 18 10:53:08 +0100] - Broadcast {"opcode":"uuid","message":"538e3fba-9a85-4ac4-b495-f688eeaa6267"}
[Wed, 21 Nov 18 10:53:08 +0100] - Received bytes = 91
[Wed, 21 Nov 18 10:53:08 +0100] - Broadcast {"opcode":"broadcast","message":"hallo11 from :538e3fba-9a85-4ac4-b495-f688eeaa6267"}
[Wed, 21 Nov 18 10:53:08 +0100] - Received bytes = 91
[Wed, 21 Nov 18 10:53:08 +0100] - Broadcast {"opcode":"broadcast","message":"hallo22 from :538e3fba-9a85-4ac4-b495-f688eeaa6267"}
[Wed, 21 Nov 18 10:53:08 +0100] - Received bytes = 91
[Wed, 21 Nov 18 10:53:08 +0100] - Broadcast {"opcode":"broadcast","message":"hallo33 from :538e3fba-9a85-4ac4-b495-f688eeaa6267"}
[Wed, 21 Nov 18 10:53:08 +0100] - Received bytes = 91
[Wed, 21 Nov 18 10:53:08 +0100] - Broadcast {"opcode":"broadcast","message":"hallo44 from :538e3fba-9a85-4ac4-b495-f688eeaa6267"}

</pre>

### Step 2

Open another browser window(2) an run the URL adressing  testWithPHPSockets.php.
You should now see in browser window(1) messages coming from the page in browser window(2).
The UUID you see will be different of course ...

<pre>
hallo11 from :7be0dc5f-0c3e-43cd-a2cb-d5e6cd7bf784
hallo22 from :7be0dc5f-0c3e-43cd-a2cb-d5e6cd7bf784
hallo33 from :7be0dc5f-0c3e-43cd-a2cb-d5e6cd7bf784
hallo44 from :7be0dc5f-0c3e-43cd-a2cb-d5e6cd7bf784
</pre>

### Step3

Now run <code>phpClient/testWithPHPSocket.php</code> from a browser window or command window.
You should now see in browser window(1) and (2) the messages below.

<pre>
hallo from PHP
hallo from PHP
hallo from PHP
hallo from PHP
hallo from PHP
</pre>