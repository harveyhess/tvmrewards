FROM php:8.1-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy your PHP app to Apache's public directory
COPY . /var/www/html
COPY ./includes /var/www/html/includes
COPY ./start.sh /start.sh

# Make start.sh executable
RUN chmod +x /start.sh

# Set working directory and start script
WORKDIR /var/www/html
CMD ["/start.sh"]
