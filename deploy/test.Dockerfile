# Test runner image: PHP CLI + the same extensions as production + composer dev dependencies
# (bcmath is only needed by a dev dependency, dragon-code/support via laravel-lang).
# Only composer.json/composer.lock are copied, so this layer is cached until dependencies change;
# the application source is bind-mounted at runtime by compose.test.yaml.
FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        git \
        unzip \
    && docker-php-ext-install intl mbstring zip pdo pdo_mysql bcmath \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

# --no-scripts: artisan/app source is not in the image yet (it is mounted at runtime).
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

# Fingerprint of the lock file vendor/ was installed from; the entrypoint compares it with the
# mounted composer.lock and asks for a rebuild when dependencies have changed.
RUN sha1sum composer.lock > /opt/composer.lock.sha1

# Empty .env: all test configuration comes from compose.test.yaml + phpunit.xml. Without the
# file, phpdotenv's read attempt surfaces as a PHPUnit warning in every HTTP test.
RUN touch .env
