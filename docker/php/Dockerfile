FROM php:8.5-apache

# PostgreSQL driver for PHP
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# Apache: serve app/public, enable pretty URLs (.htaccess)
ENV APACHE_DOCUMENT_ROOT=/var/www/app/public
RUN a2enmod rewrite \
 && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY app /var/www/app
COPY db /var/www/db
WORKDIR /var/www/app
