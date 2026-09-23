# ==========================================
# Stage 1: Base Image (FrankenPHP & PHP 8.5)
# ==========================================
FROM dunglas/frankenphp:1-php8.5 AS base

# Install required PHP extensions for Laravel & PostgreSQL
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pcntl \
    bcmath \
    intl \
    zip \
    opcache

WORKDIR /app

# Configure PHP production defaults
RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini \
    && sed -i 's/variables_order = "GPCS"/variables_order = "EGPCS"/' $PHP_INI_DIR/php.ini

# Configure OPcache for Laravel Octane
RUN echo "opcache.enable=1" >> $PHP_INI_DIR/conf.d/opcache.ini \
    && echo "opcache.enable_cli=1" >> $PHP_INI_DIR/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> $PHP_INI_DIR/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> $PHP_INI_DIR/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> $PHP_INI_DIR/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> $PHP_INI_DIR/conf.d/opcache.ini \
    && echo "opcache.save_comments=1" >> $PHP_INI_DIR/conf.d/opcache.ini

# ==========================================
# Stage 2: Builder (Dependencies & Assets)
# ==========================================
FROM base AS builder

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install Node.js & npm from official Node image
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Install PHP dependencies without dev packages
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy application source
COPY . .

# Generate autoload and Wayfinder routes
RUN composer dump-autoload --optimize --no-dev \
    && php artisan wayfinder:generate

# Install Node dependencies and build production assets
RUN npm ci || npm install
RUN npm run build

# Remove development node_modules to keep final image clean
RUN rm -rf node_modules

# ==========================================
# Stage 3: Production Runner
# ==========================================
FROM base AS runner

ENV AUTORUN_ENABLED=false \
    OCTANE_SERVER=frankenphp \
    LARAVEL_OCTANE=1

# Copy application and built assets from builder
COPY --from=builder /app /app

# Ensure runtime directories exist with proper permissions
RUN mkdir -p \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs \
    /app/bootstrap/cache \
    /config \
    /data \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /config /data \
    && chmod -R 775 /app/storage /app/bootstrap/cache \
    && php artisan storage:link

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Run as non-root user
USER www-data

# Expose Octane HTTP port
EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Default command starts Laravel Octane with FrankenPHP
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
