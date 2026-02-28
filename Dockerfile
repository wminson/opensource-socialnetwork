FROM php:8.2-apache

# Install PHP extensions required by OSSN
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libcurl4-openssl-dev \
    libxml2-dev \
    libzip-dev \
    libonig-dev \
    libexif-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        pdo \
        pdo_mysql \
        curl \
        gd \
        simplexml \
        fileinfo \
        mbstring \
        exif \
        zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite expires

# PHP configuration
RUN { \
    echo 'memory_limit=512M'; \
    echo 'post_max_size=105M'; \
    echo 'upload_max_filesize=100M'; \
    echo 'default_charset=UTF-8'; \
    echo 'allow_url_fopen=On'; \
    echo 'session.cookie_httponly=On'; \
} > /usr/local/etc/php/conf.d/ossn.ini

# Apache: listen on port 6050 so internal self-curl matches the external port
RUN sed -i 's/Listen 80/Listen 6050/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:6050>/' /etc/apache2/sites-enabled/000-default.conf

# Apache config - allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Create data directory outside web root
RUN mkdir -p /var/ossn_data && chown www-data:www-data /var/ossn_data

# Download CDN dependencies for offline use
RUN mkdir -p /tmp/vendors/jquery-ui /tmp/vendors/fancybox /tmp/vendors/fontawesome/css /tmp/vendors/fontawesome/webfonts \
    && curl -sL "https://ajax.googleapis.com/ajax/libs/jqueryui/1.14.1/jquery-ui.min.js" -o /tmp/vendors/jquery-ui/jquery-ui.min.js \
    && curl -sL "https://code.jquery.com/ui/1.14.1/themes/smoothness/jquery-ui.css" -o /tmp/vendors/jquery-ui/jquery-ui.css \
    && curl -sL "https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js" -o /tmp/vendors/fancybox/jquery.fancybox.min.js \
    && curl -sL "https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.css" -o /tmp/vendors/fancybox/jquery.fancybox.min.css \
    && curl -sL "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" -o /tmp/vendors/fontawesome/css/all.min.css \
    && for f in fa-solid-900.woff2 fa-regular-400.woff2 fa-brands-400.woff2; do \
         curl -sL "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/$f" -o "/tmp/vendors/fontawesome/webfonts/$f"; \
       done \
    && sed -i 's|url(../webfonts/|url(../fontawesome/webfonts/|g' /tmp/vendors/fontawesome/css/all.min.css

# Copy application files
COPY --chown=www-data:www-data . /var/www/html/

# Copy downloaded vendor files into app
RUN cp -r /tmp/vendors/* /var/www/html/vendors/ && rm -rf /tmp/vendors

# Ensure writable directories
RUN chown -R www-data:www-data /var/www/html/configurations \
    && rm -rf /var/www/html/cache \
    && chown www-data:www-data /var/www/html/

# Copy .htaccess from dist
RUN cp /var/www/html/installation/configs/htaccess.dist /var/www/html/.htaccess \
    && chown www-data:www-data /var/www/html/.htaccess

# Entrypoint handles auto-installation
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 6050
ENTRYPOINT ["docker-entrypoint.sh"]
