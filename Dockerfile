# Dockerfile - Docker image configuration for Defect Tracker web service
# This creates a PHP Apache container with necessary extensions for the application

FROM php:8.1-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_mysql mysqli zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Set proper permissions for www-data user
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Copy application files (will be overridden by volume mount in development)
COPY . /var/www/html

# Create necessary directories for the application
RUN mkdir -p /var/www/html/uploads/defects \
    && mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/exports \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/exports

# Expose port 80
EXPOSE 80

# Switch to www-data user for security
USER www-data

# Start Apache
CMD ["apache2-foreground"]