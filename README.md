## Docs:
docker exec -it app bash

php artisan key:generate
php artisan config:cache
php artisan migrate

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=hostel
DB_USERNAME=root
DB_PASSWORD=root