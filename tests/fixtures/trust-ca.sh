#!/bin/sh
set -eu
if [ -d /var/www/html/var/cache ]; then
  chown -R www-data:www-data /var/www/html/var/cache
  chmod -R a+rwX /var/www/html/var/cache
fi
if [ -r /run/cloud-ca.crt ]; then
  for mode in dev prod; do
    mkdir -p "/var/www/html/var/cache/$mode"
    cp /run/cloud-ca.crt "/var/www/html/var/cache/$mode/cacert.pem"
    chown www-data:www-data "/var/www/html/var/cache/$mode/cacert.pem"
  done
fi
