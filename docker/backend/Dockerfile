# syntax=docker/dockerfile:1.7
ARG PHP_VERSION=8.4-rc-fpm-alpine3.20

FROM composer:2.7 AS composer_dev
WORKDIR /app
COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

FROM composer:2.7 AS composer_prod
WORKDIR /app
COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts

FROM php:${PHP_VERSION} AS backend-base
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev zip unzip curl git bash \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl opcache \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb
COPY --from=composer_prod /usr/bin/composer /usr/local/bin/composer
WORKDIR /srv/app
ENV COMPOSER_ALLOW_SUPERUSER=1

FROM backend-base AS backend-dev
ENV APP_ENV=dev
COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts
COPY backend/ .
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]

FROM backend-base AS backend-prod
ENV APP_ENV=prod APP_DEBUG=0
COPY backend/ .
COPY --from=composer_prod /app/vendor /srv/app/vendor
RUN composer dump-autoload --classmap-authoritative --no-dev --no-interaction --optimize \
    && php bin/console cache:clear --no-warmup \
    && php bin/console cache:warmup \
    && rm -rf var/cache/test var/log/*
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
