FROM php:8.5-cli

RUN docker-php-ext-install pdo_mysql bcmath pcntl
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
ENV COMPOSER_HOME=/tmp/composer
ENV XDG_CONFIG_HOME=/tmp/coffeeshop-config
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
