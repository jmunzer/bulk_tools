FROM php:7-apache

COPY config/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY scripts/start-apache /usr/local/bin

# Application source
COPY ./src /var/www/html
RUN chown -R www-data:www-data /var/www/html

CMD ["start-apache"]