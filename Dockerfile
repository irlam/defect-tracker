# Dockerfile
# This file defines the Docker image for the PHP-based defect tracker application.
# It creates a container with PHP 8.2, Apache web server, and all required extensions.
# The image is designed for local development with live code editing via volume mounts.

FROM php:8.2-apache

# Install system dependencies and PHP extensions required for the defect tracker
# This includes database connectivity, file handling, XML processing, and more
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    xml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer (PHP dependency manager)
# This allows managing PHP packages and autoloading
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite for clean URLs and routing
# This is essential for most PHP applications that use .htaccess files
RUN a2enmod rewrite

# Set the working directory to the web root
# All application files will be mounted or copied here
WORKDIR /var/www/html

# Configure Apache to allow .htaccess overrides
# This ensures that URL rewriting and other .htaccess directives work properly
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Set proper ownership and permissions for the web directory
# The www-data user is the default Apache user in this image
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80 for the web server
EXPOSE 80

# Note: In development, the source code is mounted as a volume from the host
# This allows live editing - changes made on the host are immediately reflected in the container
# For production, you would COPY the application files into the image instead

# Start Apache in the foreground (required for Docker containers)
CMD ["apache2-foreground"]