FROM php:8.5-fpm-bookworm

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV APP_ENV=production

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    ca-certificates \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libonig-dev \
    libmagickwand-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        gd \
        pcntl \
        sockets \
        opcache \
    && pecl install redis mongodb imagick \
    && docker-php-ext-enable redis mongodb imagick \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/production/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/production/php/php.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/production/php/entrypoint.sh /usr/local/bin/proconnect-entrypoint

RUN chmod +x /usr/local/bin/proconnect-entrypoint

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .

RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

RUN composer dump-autoload \
    --optimize \
    --no-dev \
    --no-interaction \
    --no-scripts

USER www-data

EXPOSE 9000

ENTRYPOINT ["proconnect-entrypoint"]

CMD ["php-fpm", "-F"]
