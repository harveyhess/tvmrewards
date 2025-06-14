FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy app files into container
COPY . /var/www/html/

# Copy custom php.ini (optional)
# COPY php.ini /usr/local/etc/php/

# Set working directory
WORKDIR /var/www/html/

# Expose port
EXPOSE 80

# Start script (if needed)
CMD ["bash", "./start.sh"]
