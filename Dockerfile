FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock* ./
COPY src ./src
RUN composer install --no-dev --prefer-dist --no-progress --optimize-autoloader

FROM php:8.3-cli-alpine
WORKDIR /app
COPY --from=deps /app/vendor ./vendor
COPY src ./src
COPY public ./public
RUN adduser -D app
USER app
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
