FROM php:7.2.5-apache
RUN docker-php-ext-install pdo_mysql
COPY docker/app/php.ini $PHP_INI_DIR/conf.d/
COPY docker/app/apache2.conf /etc/apache2/
COPY docker/app/000-default.conf /etc/apache2/sites-available
WORKDIR /var/www/html
RUN mkdir okura-restful-api
COPY ./ /var/www/html/okura-restful-api/
WORKDIR /var/www/html/okura-restful-api/
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer
#composerをrootユーザーで実行をできるように以下の環境変数を設定
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_NO_INTERACTION 1

RUN apt-get update && apt-get install -y git
RUN apt-get install -y zip unzip
RUN composer install
RUN mkdir /var/www/html/okura-restful-api/storage
RUN mkdir /var/www/html/okura-restful-api/storage/framework
RUN mkdir /var/www/html/okura-restful-api/storage/framework/cache /var/www/html/okura-restful-api/storage/framework/sessions /var/www/html/okura-restful-api/storage/framework/views
RUN chmod -R a+w /var/www/html/
RUN chmod -R a+w /var/www/html/okura-restful-api/storage/framework/ /var/www/html/okura-restful-api/ bootstrap/cache
RUN a2enmod rewrite