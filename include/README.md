
## adressPort.inc.php

Holds adress of the server  
Used only by php/web testclients

<b>NOTE:</b>
Specify the  adress like.
<ul>
<li> ssl://xyzabc.worldserver.net[:port]   
<li> wss://xyzabc.worldserver.net[:port]
<li> ws://xyzabc.worldserver.net [:port]  
<li> tcp://xyzabc.worldserver.net[:port]
</ul>  

If no port is given default is 443 fro ssl:// wss://  
adn 80 for ws:// tcp://

