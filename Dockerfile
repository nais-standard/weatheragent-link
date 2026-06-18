FROM php:8.4-apache

RUN a2enmod rewrite
RUN docker-php-ext-install opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY . .

RUN chown -R www-data:www-data storage/

COPY public/.htaccess /var/www/html/public/.htaccess

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

HEALTHCHECK --interval=30s --timeout=5s CMD curl -f http://localhost/health || exit 1

EXPOSE 80
