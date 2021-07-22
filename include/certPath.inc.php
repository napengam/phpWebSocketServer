<?php

/*
 *      _               _      _       
   ___ | |__  ___  ___ | | ___| |_ ___
  / _ \| '_ \/ __|/ _ \| |/ _ \ __/ _ \
 | (_) | |_) \__ \ (_) | |  __/ ||  __/
  \___/|_.__/|___/\___/|_|\___|\__\___|

 * 
 * 
  #
  # script for linux shell to merge key and certificate into one file
  # as this is used by PHP in case you use https:// to communicate with
  # your web server
  #
  openssl pkcs12 -export -in cert.pem -inkey privkey.pem -out tmp.p12
  openssl pkcs12 -in tmp.p12 -nodes -out certKey.pem
  rm tmp.p12

 */
//$keyAndCertFile = '/etc/letsencrypt/live/your.server.net/certKey.pem';
//$pathToCert = '/etc/letsencrypt/live/your.server.net/';
/***********************************************************************************/

/*
 * ***********************************************
 * as of 2021-07-21 using these two files instead of above
 * ***********************************************
 */
$certFile = '/etc/letsencrypt/live/your.server.net/cert.pem';
$pkFile = '/etc/letsencrypt/live/your.server.net/privkey.pem';
