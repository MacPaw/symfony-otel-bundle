FROM php:8.2-fpm-alpine

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    bash \
    make

# Install PHP extensions
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    xml

# Install Xdebug for code coverage
# Note: xdebug 3.3.1 may fail to compile with certain PHP headers; use 3.3.2+
RUN install-php-extensions xdebug

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Expose port
EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "test_app/public"]
