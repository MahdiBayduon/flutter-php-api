FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

# Tell Apache to listen on Railway's assigned port
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf
RUN sed -i 's/:80/:${PORT}/' /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/

CMD ["apache2-foreground"]
