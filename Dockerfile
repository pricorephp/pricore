# Multi-stage Dockerfile for Laravel application with FrankenPHP

# Stage 1: Build frontend assets
FROM node:22-alpine AS frontend-builder

WORKDIR /app

# Install PHP for Wayfinder generation
RUN apk add --no-cache \
    php84 \
    php84-cli \
    php84-common \
    php84-json \
    php84-mbstring \
    php84-xml \
    php84-tokenizer \
    php84-phar \
    php84-openssl \
    php84-pdo \
    php84-pdo_sqlite \
    php84-pdo_mysql \
    php84-pdo_pgsql \
    php84-session \
    php84-curl \
    php84-fileinfo \
    php84-zip \
    php84-dom \
    php84-simplexml \
    php84-xmlreader \
    php84-xmlwriter \
    && ln -sf /usr/bin/php84 /usr/bin/php

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

# Create necessary directories
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

# Create minimal .env for Wayfinder (doesn't need database connection)
RUN echo "APP_NAME=Pricore" > .env && \
    echo "APP_ENV=production" >> .env && \
    echo "APP_KEY=base64:dGVzdGtleWZvcmJ1aWxkdGltZW9ubHlub3RyZWFsbHl1c2Vk" >> .env && \
    echo "APP_DEBUG=false" >> .env && \
    echo "DB_CONNECTION=sqlite" >> .env

# Install Composer dependencies (minimal, just for Wayfinder)
RUN apk add --no-cache curl && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs --quiet && \
    composer dump-autoload --optimize --classmap-authoritative --no-scripts --ignore-platform-reqs --quiet

# Copy frontend source files
COPY resources/ ./resources/
COPY vite.config.ts tsconfig.json ./
COPY public/ ./public/

# Build frontend assets
RUN npm run build

# Stage 2: Production application with FrankenPHP
FROM dunglas/frankenphp:1-php8.4-alpine AS application

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    sqlite-dev \
    oniguruma-dev \
    postgresql-dev \
    linux-headers \
    && install-php-extensions \
    pdo_sqlite \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    pcntl \
    bcmath \
    zip \
    opcache \
    redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy minimal files needed for composer scripts
COPY composer.json composer.lock ./
COPY artisan ./
COPY bootstrap/ ./bootstrap/
COPY app/ ./app/
COPY routes/ ./routes/
COPY config/ ./config/
COPY database/ ./database/

# Create storage directories needed for Laravel bootstrap
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy remaining application files
COPY . .

# Copy built frontend assets from frontend-builder stage
COPY --from=frontend-builder /app/public/build ./public/build

# Set proper permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

# Create SQLite database directory
RUN mkdir -p /app/database && touch /app/database/database.sqlite \
    && chown -R www-data:www-data /app/database

# PHP production configuration
RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/99-production.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "opcache.jit=1255" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "opcache.jit_buffer_size=128M" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "expose_php=Off" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "display_errors=Off" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "display_startup_errors=Off" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "log_errors=On" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "error_log=/app/storage/logs/php-errors.log" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "max_execution_time=30" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "realpath_cache_size=4096K" >> /usr/local/etc/php/conf.d/99-production.ini && \
    echo "realpath_cache_ttl=600" >> /usr/local/etc/php/conf.d/99-production.ini

# Create Caddyfile for FrankenPHP
RUN echo ':8000 {' > /etc/caddy/Caddyfile && \
    echo '    root * /app/public' >> /etc/caddy/Caddyfile && \
    echo '    encode zstd gzip' >> /etc/caddy/Caddyfile && \
    echo '' >> /etc/caddy/Caddyfile && \
    echo '    # Health check endpoint' >> /etc/caddy/Caddyfile && \
    echo '    respond /health 200' >> /etc/caddy/Caddyfile && \
    echo '' >> /etc/caddy/Caddyfile && \
    echo '    # Handle PHP requests' >> /etc/caddy/Caddyfile && \
    echo '    php_server' >> /etc/caddy/Caddyfile && \
    echo '}' >> /etc/caddy/Caddyfile

# Expose port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

# FrankenPHP handles graceful shutdown via SIGTERM
STOPSIGNAL SIGTERM

# Start FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]