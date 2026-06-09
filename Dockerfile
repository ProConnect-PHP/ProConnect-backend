FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash curl git unzip libzip-dev icu-dev oniguruma-dev \
    postgresql-dev linux-headers autoconf g++ make nginx npm

RUN docker-php-ext-install pdo pdo_pgsql intl opcache
RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080
CMD ["/start.sh"]
