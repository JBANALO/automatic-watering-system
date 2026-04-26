FROM php:8.2-apache

# Install MySQLi extension and required libraries
RUN apt-get update && apt-get install -y \
    libmariadb-dev-compat \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite
RUN a2enmod env

# Configure PHP to pass environment variables
RUN echo "variables_order = \"EGPCS\"" >> /usr/local/etc/php/conf.d/variables.ini

WORKDIR /var/www/html

COPY . /var/www/html/

# Create Apache configuration to expose all env vars
RUN echo "PassEnv MYSQL_URL" >> /etc/apache2/apache2.conf && \
    echo "PassEnv MYSQL_DATABASE" >> /etc/apache2/apache2.conf && \
    echo "PassEnv MYSQL_ROOT_PASSWORD" >> /etc/apache2/apache2.conf && \
    echo "PassEnv MYSQLHOST" >> /etc/apache2/apache2.conf && \
    echo "PassEnv MYSQLUSER" >> /etc/apache2/apache2.conf && \
    echo "PassEnv MYSQLPASSWORD" >> /etc/apache2/apache2.conf && \
    echo "PassEnv MYSQLDATABASE" >> /etc/apache2/apache2.conf && \
    echo "PassEnv MYSQLPORT" >> /etc/apache2/apache2.conf

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
