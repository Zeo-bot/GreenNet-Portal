FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    nginx \
    sqlite \
    sqlite-dev \
    curl \
    git \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip

RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    mbstring \
    intl \
    zip

WORKDIR /var/www

CMD ["php-fpm"]