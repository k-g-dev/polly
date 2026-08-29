ARG PHP_VERSION=8.5-fpm
ARG NODE_VERSION=24-alpine
ARG NGINX_VERSION=1.30.2-alpine

FROM php:${PHP_VERSION} AS base

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    libicu-dev \
    libzip-dev \
    gcc \
    g++ \
    make \
    autoconf \
 && docker-php-ext-install intl pdo_mysql zip \
 && pecl install apcu \
 && docker-php-ext-enable apcu \
 && echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini \
 && rm -rf /tmp/pear \
 && apt-get purge -y --auto-remove gcc g++ make autoconf \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- DEVELOPMENT ---
FROM base AS development

ARG HOST_UID=1000
ARG HOST_GID=1000

RUN usermod -u ${HOST_UID} www-data && groupmod -g ${HOST_GID} www-data
RUN mkdir -p /var/www/.composer && chown -R www-data:www-data /var/www/.composer

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    && rm -rf /var/lib/apt/lists/*

RUN git config --system core.preloadindex false

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

# --- COMPOSER PROD ---
FROM base AS composer_production

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    && rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --no-cache

# --- FRONTEND PROD ---
FROM node:${NODE_VERSION} AS frontend_production

WORKDIR /var/www/html

COPY package.json yarn.lock webpack.config.js ./

RUN yarn install --frozen-lockfile

COPY assets/ ./assets/

RUN yarn build

# --- PRODUCTION ---
FROM base AS production

ARG DB_VERSION=12.3.2

ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV DATABASE_URL="mysql://null:null@127.0.0.1:3306/null?serverVersion=${DB_VERSION}-MariaDB&charset=utf8mb4"

COPY docker/php/prod-php.ini /usr/local/etc/php/conf.d/zzz-prod-php.ini

COPY --chown=www-data:www-data . .
COPY --from=composer_production --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=frontend_production --chown=www-data:www-data /var/www/html/public/build ./public/build

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer dump-env prod --empty

RUN mkdir -p var/cache var/log \
    && php bin/console assets:install public --no-interaction \
    && php bin/console cache:clear \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

# --- WEB SERVER PROD ---
FROM nginx:${NGINX_VERSION} AS nginx_production

WORKDIR /var/www/html

COPY docker/nginx/prod.conf /etc/nginx/conf.d/default.conf

COPY --from=production /var/www/html/public ./public

ENTRYPOINT []

CMD ["nginx", "-g", "daemon off;"]
