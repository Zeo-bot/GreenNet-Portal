FROM php:8.3-fpm-alpine AS extensions

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        sqlite-dev \
        su-exec \
    && docker-php-ext-install \
        intl \
        mbstring \
        pdo \
        pdo_sqlite \
        zip

FROM php:8.3-fpm-alpine AS application

RUN apk add --no-cache \
        icu-libs \
        libzip \
        oniguruma \
        sqlite-libs \
        su-exec

COPY --from=extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

WORKDIR /var/www

COPY src/app ./app
COPY src/public ./public
COPY bin ./bin
COPY docker/production/php-entrypoint.sh /usr/local/bin/greennet-entrypoint
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/zz-greennet.conf
COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-greennet.ini

RUN chmod 0755 /usr/local/bin/greennet-entrypoint \
    && rm -f /var/www/public/reset-admin-once.php \
    && mkdir -p database public/uploads storage/backups \
    && chown -R www-data:www-data /var/www

ENTRYPOINT ["greennet-entrypoint"]
CMD ["php-fpm", "-F"]

FROM application AS server

FROM application AS mikrotik

RUN apk add --no-cache nginx

COPY docker/mikrotik/nginx.conf /etc/nginx/nginx.conf
COPY docker/mikrotik/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-mikrotik.conf
COPY docker/mikrotik/php.ini /usr/local/etc/php/conf.d/zzz-mikrotik.ini
COPY docker/mikrotik/runtime.sh /usr/local/bin/greennet-mikrotik-runtime

RUN chmod 0755 /usr/local/bin/greennet-mikrotik-runtime \
    && mkdir -p /run/nginx /var/lib/nginx /var/log/nginx \
    && chown -R nginx:nginx /run/nginx /var/lib/nginx /var/log/nginx

CMD ["greennet-mikrotik-runtime"]
