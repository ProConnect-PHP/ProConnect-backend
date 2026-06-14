FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash curl git unzip libzip-dev icu-dev oniguruma-dev \
    postgresql-dev linux-headers autoconf g++ make nginx npm

RUN docker-php-ext-install pdo pdo_pgsql pgsql bcmath intl zip gd opcache pcntl sockets

RUN pecl install redis mongodb \
    && docker-php-ext-enable redis mongodb

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080
CMD ["/start.sh"]
