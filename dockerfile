# Use the official PHP image
FROM php:8.4-apache

RUN apt-get update -y && apt-get upgrade -y \
    && apt-get install -y libzip-dev zip \
    && docker-php-ext-install zip ctype iconv pdo_mysql \
    && a2enmod rewrite proxy proxy_http \
    && ln -sf /bin/bash /bin/sh \
    && apt-get install -y git

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install symfony console
RUN curl -sS https://get.symfony.com/cli/installer | bash

# Install nvm and Node.js
ENV NVM_DIR=/root/.nvm
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash \
    && . "$NVM_DIR/nvm.sh" \
    && nvm install 22 \
    && nvm use 22 \
    && nvm alias default 22 \
    && ln -s "$NVM_DIR/versions/node/$(nvm version)/bin/node" /usr/bin/node \
    && ln -s "$NVM_DIR/versions/node/$(nvm version)/bin/npm" /usr/bin/npm

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable and configure OPcache
RUN docker-php-ext-install opcache