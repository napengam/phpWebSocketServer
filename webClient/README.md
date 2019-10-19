
# NOTE


Use at own risc. 


## socketWebClient.js

JavaScript to connect to the server.


This script also sends very long messages in chunks of `chunksize=6+1204`.  

## testWithWebSockets.php

Have the server started and waiting ....

**Step 1**

In a browser window open `http://your.web.server/testWithWebSockets.php`.  
You will see this when you perform **Step 2**

![webApp](w1.png)

**Step 2**

In an other browser window open `http://your.web.server/testWithWebSockets.php`.  
This will broadcast into the page you opened  in **Step 1**.  
Press the button in the page you opened in **Step 1** then you will see the output below

![webApp](w2.png)

