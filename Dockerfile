# ── Stage 1: Builder ──────────────────────────────────────
# Stage ini hanya untuk install dependencies, tidak masuk ke image final
FROM composer:2.7 AS builder

WORKDIR /app

# Copy file manifest dulu (memanfaatkan layer cache Docker)
COPY composer.json composer.lock ./

# Install dependencies tanpa autoloader, tanpa dev packages
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copy seluruh kode aplikasi
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# ── Stage 2: Production image ─────────────────────────────
FROM php:8.2-fpm-alpine

LABEL maintainer="nama-anda@email.com"

# Install ekstensi PHP yang dibutuhkan Laravel
RUN apk add --no-cache \
        libpng-dev \
        libjpeg-turbo-dev \
        libzip-dev \
        oniguruma-dev \
        icu-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        intl \
        opcache

# Konfigurasi OPcache untuk production
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.memory_consumption=192'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy hasil build dari stage 1
COPY --from=builder /app /var/www/html

# Set permission yang benar
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
