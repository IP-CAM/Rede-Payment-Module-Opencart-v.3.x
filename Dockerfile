FROM php:7.3-apache

ENV COMPOSER_HOME=/var/www/composer

# Update and install
RUN apt-get update

RUN apt-get install -y --no-install-recommends libjpeg-dev libpng-dev libzip-dev vim unzip wget
RUN docker-php-ext-configure gd --with-png-dir=/usr --with-jpeg-dir=/usr
RUN docker-php-ext-install gd mysqli opcache zip

RUN mv /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
RUN sed -i 's/memory_limit.*/memory_limit=-1/' /usr/local/etc/php/php.ini

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"

run chown -R 1000.1000 /var/www
RUN chmod ug=rwx+s /var/www

USER 1000
RUN wget https://github.com/opencart/opencart/releases/download/3.0.3.6/opencart-3.0.3.6.zip -P /var/www/
RUN unzip /var/www/opencart-3.0.3.6.zip -d /var/www/opencart
RUN cp -R /var/www/opencart/upload/* /var/www/html
RUN cp /var/www/opencart/composer.json /var/www/html
RUN mv /var/www/html/config-dist.php /var/www/html/config.php
RUN mv /var/www/html/admin/config-dist.php /var/www/html/admin/config.php
RUN rm /var/www/opencart-3.0.3.6.zip
RUN rm -rf /var/www/opencart

RUN composer install --no-dev
RUN composer require "developersrede/erede-php"
RUN composer require "monolog/monolog"

USER root

# User settings
RUN usermod -u 1000 www-data
RUN groupmod -g 1000 www-data
