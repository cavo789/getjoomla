# Desired target PHP version, See https://hub.docker.com/_/php for more
ARG PHP_VERSION=7.4

# Version number of xDebug to install.
ARG XDEBUG_VERSION=2.9.8

FROM php:${PHP_VERSION}-fpm

# Install xDebug
ARG XDEBUG_VERSION

RUN pecl install -f xdebug-${XDEBUG_VERSION}; \
    rm -r /tmp/pear/*; \
    docker-php-ext-enable xdebug

# Install ZipArchive 
RUN apt-get update ; apt-get install -y libzip-dev zip && docker-php-ext-install zip

WORKDIR /var/www/html

RUN chmod -R 0777 .

COPY ./src/index.php index.php
