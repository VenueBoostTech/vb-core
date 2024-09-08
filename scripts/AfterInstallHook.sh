#!/bin/bash
cd /var/www/html/core.sn-boost.com
php artisan migrate
composer dump-autoload
php artisan optimize:clear
sleep 200
curl -I localhost