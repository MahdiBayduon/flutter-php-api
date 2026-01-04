FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

# Tell Apache to listen on Railway's dynamic port
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf
RUN sed -i 's/:80/:${PORT}/' /etc/apache2/sites-available/000-default.conf

# Force Apache to bind to 0.0.0.0
RUN sed -i 's/Listen ${PORT}/Listen 0.0.0.0:${PORT}/' /etc/apache2/ports.conf

COPY . /var/www/html/

CMD ["apache2-foreground"]
