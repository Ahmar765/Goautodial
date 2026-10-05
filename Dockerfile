FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" mysqli curl gd intl mbstring xml zip bcmath opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY deployment/php-production.ini /usr/local/etc/php/conf.d/99-goautodial.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . /var/www/html/
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-plugins \
    && chown -R root:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 0750 {} + \
    && find /var/www/html -type f -exec chmod 0640 {} + \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/img/avatars

EXPOSE 80
