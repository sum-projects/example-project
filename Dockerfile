# syntax=docker/dockerfile:1
ARG FRANKENPHP_TAG=1.13-php8.4-trixie

# ---- base ----
FROM dunglas/frankenphp:${FRANKENPHP_TAG} AS base

WORKDIR /app

RUN install-php-extensions @composer-2 apcu intl opcache pdo_pgsql zip

COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/php/conf.d/app.ini $PHP_INI_DIR/conf.d/app.ini

HEALTHCHECK --start-period=30s --interval=10s --timeout=5s --retries=5 \
    CMD curl -f http://localhost:2019/metrics || exit 1

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# ---- dev ----
FROM base AS dev

ARG UID=1000
ARG GID=1000

ENV XDEBUG_MODE=off

RUN cp $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini \
    && install-php-extensions xdebug

COPY docker/php/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/app.dev.ini

RUN groupadd --gid ${GID} app \
    && useradd --uid ${UID} --gid ${GID} --create-home --shell /bin/bash app \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R app:app /app /data/caddy /config/caddy

USER app

# ---- prod ----
FROM base AS prod

ENV APP_ENV=prod

RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini

COPY docker/php/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/app.prod.ini

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-autoloader --no-scripts --no-progress --prefer-dist

COPY . .
RUN rm -rf docker \
    && mkdir -p var \
    && composer dump-autoload --classmap-authoritative --no-dev \
    && composer dump-env prod \
    && composer run-script --no-dev post-install-cmd

RUN groupadd --gid 1000 app \
    && useradd --uid 1000 --gid 1000 --create-home --shell /bin/bash app \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && mkdir -p /data/caddy /config/caddy var \
    && chown -R app:app /app/var /data/caddy /config/caddy

USER app
