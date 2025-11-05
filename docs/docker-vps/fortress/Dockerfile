# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.2
ARG NODE_VERSION=20
ARG APP_DIR=/var/www/html

FROM composer:2.7 AS composer_deps
ARG APP_DIR=/var/www/html
WORKDIR ${APP_DIR}

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts \
    --ignore-platform-reqs

COPY . .
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts \
    --ignore-platform-reqs
    
RUN rm -f bootstrap/cache/*.php    

FROM node:${NODE_VERSION}-alpine AS frontend

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci --prefer-offline

COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js tsconfig.json ./
RUN npm run build

FROM php:${PHP_VERSION}-fpm-bookworm AS php
ARG APP_DIR=/var/www/html
ENV APP_DIR=${APP_DIR}

WORKDIR ${APP_DIR}

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libbz2-dev \
        libzip-dev \
        libpq-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        libicu-dev \
        libwebp-dev \
        libavif-dev \
        pkg-config \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        zip \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer_deps ${APP_DIR} ${APP_DIR}
COPY --from=frontend /build/public/build ${APP_DIR}/public/build

COPY docs/docker-vps/fortress/php.ini /usr/local/etc/php/conf.d/zz-fortress.ini

RUN if [ -d storage ]; then \
        find storage -type d -print0 | xargs -0 -r chmod 775 && \
        find storage -type f -print0 | xargs -0 -r chmod 664; \
    fi && \
    if [ -d bootstrap/cache ]; then \
        chown -R www-data:www-data storage bootstrap/cache && \
        chmod -R ug+rw bootstrap/cache; \
    fi && \
    ln -sfn ${APP_DIR}/storage/app/public ${APP_DIR}/public/storage

USER www-data

CMD ["php-fpm"]

FROM nginx:1.27-alpine AS nginx
ARG APP_DIR=/var/www/html
WORKDIR /var/www/html

COPY --from=composer_deps ${APP_DIR}/public ./public
COPY --from=frontend /build/public/build ./public/build
COPY docs/docker-vps/fortress/nginx.conf /etc/nginx/conf.d/default.conf
RUN mkdir -p ${APP_DIR}/storage/app/public && \
    ln -sfn ${APP_DIR}/storage/app/public ${APP_DIR}/public/storage
