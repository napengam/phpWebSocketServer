# Example
## socketPhpClient.php

Class to connect to the server. 
This class also sends long messages in chunks of ``$chunkSize=8*1024``


## testWithPHPSockets.php

Have the server started and waiting ....

> php testWithPHPSockets.php

in the command window  where you startet the server you should see some output
like this 

>[Sat, 19 Oct 19 07:17:33 +0200] - New client connecting on socket #12  
>[Sat, 19 Oct 19 07:17:33 +0200] - Handshake:php process  
>GET /php HTTP/1.1  
>  
>  
>[Sat, 19 Oct 19 07:17:33 +0200] - Handshake with socket #17 successful  
>[Sat, 19 Oct 19 07:17:33 +0200] - Telling Client to start on  #17  
>[Sat, 19 Oct 19 07:17:33 +0200] - Received 51 Bytes from socket #17  
>[Sat, 19 Oct 19 07:17:33 +0200] - Broadcast {"opcode":"broadcast","message":"hallo from PHP 1"}  
>[Sat, 19 Oct 19 07:17:33 +0200] - Received 51 Bytes from socket #17  
>[Sat, 19 Oct 19 07:17:33 +0200] - Broadcast {"opcode":"broadcast","message":"hallo from PHP 2"}  
>[Sat, 19 Oct 19 07:17:33 +0200] - Received 51 Bytes from socket #17  
>[Sat, 19 Oct 19 07:17:33 +0200] - Broadcast {"opcode":"broadcast","message":"hallo from PHP 3"}  
>[Sat, 19 Oct 19 07:17:33 +0200] - Received 51 Bytes from socket #17  
>[Sat, 19 Oct 19 07:17:33 +0200] - Broadcast {"opcode":"broadcast","message":"hallo from PHP 4"}  
>[Sat, 19 Oct 19 07:17:33 +0200] - Received 51 Bytes from socket #17  
>[Sat, 19 Oct 19 07:17:33 +0200] - Broadcast {"opcode":"broadcast","message":"hallo from PHP 5"}  
>[Sat, 19 Oct 19 07:17:33 +0200] - Buffering ON  
>[Sat, 19 Oct 19 07:17:33 +0200] - Buffering OFF  
>[Sat, 19 Oct 19 07:17:33 +0200] - Received 9259 Bytes from socket #17  
>[Sat, 19 Oct 19 07:17:33 +0200] - Broadcast {"opcode":"broadcast","message":"PPPPPPPPPPPPPPPPPPPPPPPPP  ....  
>....PPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPP 6~6~6~6"}  
>[Sat, 19 Oct 19 07:17:33 +0200] - Socket 17 - Client disconnected - TCP connection lost  
>[Sat, 19 Oct 19 07:17:33 +0200] - Connection closed to socket #17  

If you have a web client open and running in a browser you should  
see the messages also in the web client window. 