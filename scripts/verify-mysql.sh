#!/bin/sh
set -eu

# This script uses coffee_test only; never rebuild the menu database.
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root -e "CREATE DATABASE IF NOT EXISTS coffee_test; GRANT ALL PRIVILEGES ON coffee_test.* TO '\''coffee'\''@'\''%'\'';"'

docker compose exec -T -e DB_DATABASE=coffee_test app php artisan migrate --force
docker compose exec -T -e DB_DATABASE=coffee_test app php artisan migrate:rollback --force
docker compose exec -T -e DB_DATABASE=coffee_test app php artisan migrate --force
docker compose exec -T -e DB_CONNECTION=mysql -e DB_DATABASE=coffee_test app php artisan test
