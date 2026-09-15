FROM php:8.3-cli

# Install system dependencies (git and unzip are used by Composer)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git unzip libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install zip

# Code coverage driver for PHPUnit (./docker-coverage). Only collects while PHPUnit asks it to.
RUN pecl install pcov \
    && docker-php-ext-enable pcov

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# The project is bind-mounted from the host, so its owner differs from the container user
RUN git config --system --add safe.directory '*'

WORKDIR /var/www/html
