# Desired target PHP version, See https://hub.docker.com/_/php for more
ARG PHP_VERSION=7.4

# Version number of xDebug to install.
ARG XDEBUG_VERSION=2.9.8

ARG APPLICATION_NAME=""

ARG OS_USERNAME="php_user"
ARG OS_USERID=1000

FROM php:${PHP_VERSION}-fpm

# Install xDebug
ARG XDEBUG_VERSION

RUN pecl install -f xdebug-${XDEBUG_VERSION}; \
    rm -r /tmp/pear/*; \
    docker-php-ext-enable xdebug

# Install php modules
RUN apt-get update; \
    apt-get install -y --no-install-recommends libzip-dev zip && docker-php-ext-install zip; \
    # cleanup the apt-get install cache and any tmp folder
    apt-get clean

WORKDIR /var/www

ARG OS_USERNAME
ARG OS_USERID

# Create a new user on Linux
RUN useradd -G www-data,root -u ${OS_USERID} -d /home/${OS_USERNAME} ${OS_USERNAME}
RUN mkdir -p /home/${OS_USERNAME}/.composer; \
    chown -R ${OS_USERNAME}:${OS_USERNAME} /home/${OS_USERNAME}; \
    chown -R ${OS_USERNAME}:${OS_USERNAME} /var/www; \
    chmod -R 0755 . ; \
    # Remove the default folder
    rm -rf /var/www/html

USER ${OS_USERNAME}

# Set the bash prompt; display the name of the container and the current username in the prompt
ARG APPLICATION_NAME
RUN /bin/bash -c "echo \"PS1='\e[0;33mDOCKER - ${APPLICATION_NAME}\e[0m - \e[0;36m$(whoami)\e[0m \w #  '\" >> ~/.bashrc "

# Create the folder for vscode and copy files in it (like the one required for PHP Debug)
# RUN mkdir .vscode 

COPY --chown=${OS_USERNAME}:${OS_USERNAME} .vscode/ .vscode/

# And copy our source
COPY --chown=${OS_USERNAME}:${OS_USERNAME} src/ src/
