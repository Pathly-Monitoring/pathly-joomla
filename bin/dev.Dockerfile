# PHP image used to run the checks locally, like the CI does.
#   docker build -t pathly-joomla-dev -f bin/dev.Dockerfile bin
#   docker run --rm -v "$PWD:/app" -w /app pathly-joomla-dev composer coverage
ARG PHP=8.2
FROM php:${PHP}-cli
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install zip \
    && pecl install xdebug && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
