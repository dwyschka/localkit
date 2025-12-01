FROM php:8.3-fpm
ARG NODE_VERSION=22
ARG TARGETPLATFORM


LABEL maintainer="Your Name <your.email@example.com>"

# Set working directory
WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    wget \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    libxml2-dev \
    gpg \
    ffmpeg

RUN     curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
        && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
        && apt-get update \
        && apt-get install -y nodejs \
        && npm install -g npm

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install extensions
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath intl opcache xml

# Install GD extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Nginx
RUN apt-get update && apt-get install -y nginx
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/nginx/ssl/default.crt /etc/nginx/ssl/default.crt
COPY docker/nginx/ssl/default.key /etc/nginx/ssl/default.key

# Configure PHP-FPM
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/php.ini /usr/local/etc/php/php.ini

# Install supervisor
RUN apt-get update && apt-get install -y supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create system user to run Laravel commands
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy application files
COPY --chown=www:www . /var/www/html


# Set permissions
RUN chown -R www:www /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER root
RUN cd /var/www/html && cp /var/www/html/.env.example /var/www/html/.env \
    && composer install --no-interaction --optimize-autoloader


RUN cd /var/www/html && npm install && npm run build

# Install Go2RTC

RUN set -ex; \
    case "$TARGETPLATFORM" in \
        "linux/amd64")  ARCH="amd64" ;; \
        "linux/arm64")  ARCH="arm64" ;; \
        "linux/arm/v7") ARCH="armv7" ;; \
        *) echo "Unsupported platform: $TARGETPLATFORM" && exit 1 ;; \
    esac; \
    wget -O /usr/local/bin/go2rtc \
        "https://github.com/AlexxIT/go2rtc/releases/latest/download/go2rtc_linux_${ARCH}"; \
    chmod +x /usr/local/bin/go2rtc

#

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port 80
EXPOSE 80


# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Default command - start supervisor which manages nginx and php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
