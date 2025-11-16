# Multi-stage Dockerfile for Laravel application

# Stage 1: Build frontend assets
FROM node:22-alpine AS frontend-builder

WORKDIR /app

# Install PHP and required extensions for Wayfinder plugin
RUN apk add --no-cache php php-cli php-json php-mbstring php-xml php-tokenizer php-phar php-openssl

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production=false

# Copy Composer files for Wayfinder
COPY composer.json composer.lock ./
COPY app/ ./app/
COPY bootstrap/ ./bootstrap/
COPY config/ ./config/
COPY routes/ ./routes/
COPY artisan ./

# Create minimal .env for Wayfinder (doesn't need database connection)
RUN echo "APP_NAME=Pricore" > .env && \
    echo "APP_ENV=production" >> .env && \
    echo "APP_KEY=base64:buildtimekey" >> .env && \
    echo "APP_DEBUG=false" >> .env

# Install Composer dependencies (minimal, just for Wayfinder)
RUN apk add --no-cache curl && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer install --no-dev --no-scripts --no-autoloader --quiet && \
    composer dump-autoload --optimize --classmap-authoritative --quiet

# Copy frontend source files
COPY resources/ ./resources/
COPY vite.config.ts tsconfig.json ./
COPY public/ ./public/

# Build frontend assets
RUN npm run build

# Stage 2: PHP base image with extensions
FROM php:8.4-fpm-alpine AS php-base

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    sqlite \
    oniguruma-dev \
    postgresql-dev \
    && docker-php-ext-install \
    pdo \
    pdo_sqlite \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Stage 3: Application image
FROM php-base AS application

WORKDIR /var/www/html

# Copy Composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy application files
COPY . .

# Copy built frontend assets from frontend-builder stage
COPY --from=frontend-builder /app/public/build ./public/build

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create SQLite database directory if it doesn't exist
RUN mkdir -p /var/www/html/database && touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data /var/www/html/database

# Configure PHP for production (before switching user)
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=2" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Switch to non-root user
USER www-data

# Expose port
EXPOSE 8000

# Health check (Laravel 12 has /up route by default)
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

# Start Laravel development server (for production, use nginx + PHP-FPM)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

