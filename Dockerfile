FROM php:8.2-apache

# Install MySQLi extension and required libraries
RUN apt-get update && apt-get install -y \
    libmariadb-dev-compat \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
