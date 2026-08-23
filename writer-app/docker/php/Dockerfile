FROM php:7.4-apache

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libsqlite3-dev \
        sqlite3 \
        curl \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions we actually need
RUN docker-php-ext-install pdo pdo_sqlite

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache rewrite + custom vhost
RUN a2enmod rewrite
COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf

# Application directory
WORKDIR /app

# Ensure the writer-app/data/ dir is writeable by Apache at runtime.
RUN mkdir -p /app/writer-app/data && chown -R www-data:www-data /app/writer-app/data

EXPOSE 80
