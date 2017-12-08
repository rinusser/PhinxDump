FROM php:7.1-cli
RUN docker-php-ext-install pdo_mysql
ADD src /app
ENTRYPOINT ["php", "-f", "/app/main.php"]
