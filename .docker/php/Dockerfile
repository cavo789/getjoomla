# Desired target PHP version, See https://hub.docker.com/_/php for more
ARG PHP_VERSION=7.4

# Should we install xDebug?
ARG XDEBUG_INSTALL=false

# If yes, which version to install?
ARG XDEBUG_FULL_VERSION=3.0.4

# The application name like f.i. "MySample"
ARG APPLICATION_NAME=""

# The name and the ID of the user on the operation system
ARG OS_USERNAME="Christophe"
ARG OS_USERID=1000

# We'll use the FPM version; i.e. without Apache
# We'll create a separate Docker service for Apache or nginx
FROM php:${PHP_VERSION}-fpm

# xDebug - start #####################################################
ARG XDEBUG_INSTALL
ARG XDEBUG_FULL_VERSION

RUN echo "Install xDebug? $XDEBUG_INSTALL (version ${XDEBUG_FULL_VERSION})"; \
    if [ "$XDEBUG_INSTALL" = "true" ]; then \
    pecl install xdebug-${XDEBUG_FULL_VERSION}; \
    docker-php-ext-enable xdebug; \
    rm -r /tmp/pear/*; \
    fi
# xDebug - end #######################################################

# Install some php modules
RUN apt-get update; \
    apt-get install -y --no-install-recommends libzip-dev zip && docker-php-ext-install zip; \
    # cleanup the apt-get install cache and any tmp folder
    apt-get clean

WORKDIR /var/www

ARG OS_USERNAME
ARG OS_USERID

# Create and configure a new user on Linux - start ###################
RUN useradd -G www-data,root -u ${OS_USERID} -d /home/${OS_USERNAME} ${OS_USERNAME}
RUN mkdir -p /home/${OS_USERNAME}/.composer; \
    chown -R ${OS_USERNAME}:${OS_USERNAME} /home/${OS_USERNAME}; \
    chown -R ${OS_USERNAME}:${OS_USERNAME} /var/www; \
    chmod -R 0755 .

USER ${OS_USERNAME}

# Set the bash prompt; display the name of the container and the current username in the prompt
ARG APPLICATION_NAME
RUN /bin/bash -c "echo \"PS1='\e[0;33mDOCKER - ${APPLICATION_NAME}\e[0m - \e[0;36m$(whoami)\e[0m \w #  '\" >> ~/.bashrc "
# Create and configure a new user on Linux - end #####################

# Copy files from the host to Docker - start #########################
# Create the folder for vscode and copy files in it (like the one required for PHP Debug)
COPY --chown=${OS_USERNAME}:${OS_USERNAME} .vscode/ .vscode/

# And copy our source
COPY --chown=${OS_USERNAME}:${OS_USERNAME} src/ src/
# Copy files from the host to Docker - end ###########################
