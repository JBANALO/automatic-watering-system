FROM php:8.2-apache

# Install MySQLi extension and required libraries
RUN apt-get update && apt-get install -y \
    libmariadb-dev-compat \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite
RUN a2enmod env

# Prevent "More than one MPM loaded" by disabling alternate MPMs.
RUN a2dismod mpm_event mpm_worker || true

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

# Keep exactly one Apache MPM enabled at runtime to avoid startup crash.
RUN printf '%s\n' \
    '#!/bin/bash' \
    'set -e' \
    'shopt -s nullglob' \
    'mpm_loads=(/etc/apache2/mods-enabled/mpm_*.load)' \
    'if [ ${#mpm_loads[@]} -gt 1 ]; then' \
    '  keep=""' \
    '  if [ -f /etc/apache2/mods-enabled/mpm_prefork.load ]; then' \
    '    keep="/etc/apache2/mods-enabled/mpm_prefork.load"' \
    '  else' \
    '    keep="${mpm_loads[0]}"' \
    '  fi' \
    '  for f in "${mpm_loads[@]}"; do' \
    '    if [ "$f" != "$keep" ]; then' \
    '      base="${f%.load}"' \
    '      rm -f "$f" "${base}.conf"' \
    '    fi' \
    '  done' \
    'fi' \
    'exec apache2-foreground' \
    > /usr/local/bin/start-apache.sh && chmod +x /usr/local/bin/start-apache.sh

EXPOSE 80

CMD ["/usr/local/bin/start-apache.sh"]
