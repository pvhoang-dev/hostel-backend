## Docs:
docker exec -it app bash

apt update && apt install curl unzip -y
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

php artisan key:generate
php artisan config:cache
php artisan migrate

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=hostel
DB_USERNAME=root
DB_PASSWORD=root



docker compose down
docker compose build --no-cache
docker compose up -d


su - root

chown -R www:www /var/www
chmod -R 775 /var/www/storage /var/www/bootstrap/cache
exit