<?php
/*
  #
  # script ro merge key and certificate into one file
  #
  openssl pkcs12 -export -in cert.pem -inkey privkey.pem -out tmp.p12
  openssl pkcs12 -in tmp.p12 -nodes -out certKey.pem
  rm tmp.p12

 */
$keyAndCertFile = '/etc/letsencrypt/live/your.server.net/certKey.pem';
$pathToCert = '/etc/letsencrypt/live/your.server.net/';
