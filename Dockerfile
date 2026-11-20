FROM php:8.3-apache

# Install required system dependencies for GD, Exif, and MySQL/SQLite
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    libsqlite3-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure and install GD with JPEG, WebP, and FreeType support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd

# Install PDO MySQL, PDO SQLite, Exif, and Fileinfo
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_sqlite exif fileinfo

# Enable Apache mod_rewrite and headers
RUN a2enmod rewrite headers

# Copy custom PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Set working directory
WORKDIR /var/www/html

# Adjust permissions for uploads directory
RUN mkdir -p /var/www/html/uploads/originals /var/www/html/uploads/thumbnails \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 80
CMD ["apache2-foreground"]
