FROM php:apache

# Enable rewrite module
RUN a2enmod rewrite

# Install system packages, PHP zip extension, and Composer prerequisites
RUN apt-get update && \
    apt-get install -y --no-install-recommends git unzip curl libzip-dev zip && \
    docker-php-ext-install zip && \
    rm -rf /var/lib/apt/lists/*