FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Install Redis extension (compile from source to avoid pecl registry issues)
RUN curl -fsSL https://github.com/phpredis/phpredis/archive/refs/tags/6.1.0.tar.gz \
        -o /tmp/phpredis.tar.gz \
    && mkdir -p /tmp/phpredis \
    && tar -xzf /tmp/phpredis.tar.gz -C /tmp/phpredis --strip-components=1 \
    && cd /tmp/phpredis \
    && phpize \
    && ./configure \
    && make -j$(nproc) \
    && make install \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/phpredis /tmp/phpredis.tar.gz

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy PHP config
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Set permissions
RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
