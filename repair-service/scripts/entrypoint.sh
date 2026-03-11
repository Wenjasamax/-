#!/bin/sh
set -eu

# Wait for MySQL availability before migrations.
count=0
until [ $count -ge 30 ] || nc -z "${DB_HOST:-db}" "${DB_PORT:-3306}" 2>/dev/null; do
  echo "Waiting for DB..."
  sleep 2
  count=$((count + 1))
done

if [ ! -f /var/www/vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

php /var/www/scripts/migrate.php
php /var/www/scripts/seed.php

exec php-fpm
