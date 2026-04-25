FROM php:8.2-apache

# Install MySQLi extension
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Copy Apache VirtualHost configuration
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Enable the site
RUN a2ensite 000-default

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
