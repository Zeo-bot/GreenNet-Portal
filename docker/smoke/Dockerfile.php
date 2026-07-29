FROM php:8.3-fpm-alpine

WORKDIR /var/www

COPY src/app ./app
COPY src/public ./public
COPY tests/smoke/smoke.env ./.env
COPY tests/smoke/bootstrap.php /usr/local/bin/greennet-smoke-bootstrap.php

CMD ["sh", "-c", "php /usr/local/bin/greennet-smoke-bootstrap.php && chown -R www-data:www-data /var/www/database && exec php-fpm -F"]
