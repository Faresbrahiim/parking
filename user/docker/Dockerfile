FROM php:8.2-apache

# Enable Apache rewrite
RUN a2enmod rewrite

# Install MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html