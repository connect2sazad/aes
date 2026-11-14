FROM php:8.4-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql opcache \
    && a2enmod rewrite headers setenvif \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY app/ ./app/
COPY bin/ ./bin/
COPY public/ ./public/
COPY docker/config.php ./config.php
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/aes.ini
COPY docker/entrypoint.sh /usr/local/bin/aes-entrypoint
COPY docker/worker.sh docker/health.php /opt/aes/
RUN chmod 755 /usr/local/bin/aes-entrypoint /opt/aes/worker.sh \
    && mkdir -p storage/photos storage/sessions \
    && chown -R www-data:www-data storage

EXPOSE 80
ENTRYPOINT ["aes-entrypoint"]
CMD ["apache2-foreground"]

