#!/bin/sh
set -eu

# Wait for MySQL availability before migrations.
until mysql -h"${DB_HOST:-db}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-user}" -p"${DB_PASSWORD:-password}" -e "SELECT 1" >/dev/null 2>&1; do
  echo "Waiting for DB..."
  sleep 2
done

if [ ! -f /var/www/vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

php /var/www/scripts/migrate.php
php /var/www/scripts/seed.php

exec php-fpm
