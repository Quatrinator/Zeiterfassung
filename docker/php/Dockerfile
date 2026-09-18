FROM php:8.5-fpm-bookworm
RUN docker-php-ext-install pdo_mysql \
    && mkdir -p /var/lib/php/sessions \
    && chown www-data:www-data /var/lib/php/sessions \
    && php -r 'if (!defined("PASSWORD_ARGON2ID")) exit(1);'
COPY php.ini /usr/local/etc/php/conf.d/zeitwerk.ini
USER www-data
WORKDIR /var/www/app
