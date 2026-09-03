# stage 1: build frontend assets
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public
COPY app ./app
COPY config ./config
COPY bootstrap ./bootstrap
COPY routes ./routes

RUN npm run build

# stage 2: PHP app with Nginx
FROM php:8.4-fpm-bookworm AS app

# PHP extensions and system deps
RUN apt-get update && apt-get upgrade -y && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libsqlite3-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    unzip \
    git \
    && apt-get install -y --only-upgrade libgnutls30 \
    && docker-php-ext-install -j$(nproc) curl \
    && docker-php-ext-install -j$(nproc) pdo_sqlite \
    && docker-php-ext-install -j$(nproc) pdo_mysql \
    && docker-php-ext-install -j$(nproc) mbstring \
    && docker-php-ext-install -j$(nproc) xml \
    && docker-php-ext-install -j$(nproc) dom \
    && docker-php-ext-install -j$(nproc) zip \
    && docker-php-ext-install -j$(nproc) bcmath \
    # gd is configured immediately before it is installed, and nothing may run
    # between the two. docker-php-ext-configure unpacks the PHP source and
    # leaves a .docker-delete-me marker; every docker-php-ext-install deletes
    # that source when it finishes. Any install in between therefore throws the
    # configured gd away, and the later `install gd` rebuilds it with no flags
    # at all: no FreeType (OG images 500), no JPEG, no WebP.
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Fail the build rather than ship a gd that silently lost its libraries. A gd
# without FreeType 500s on every OG image, and without JPEG or WebP the proxied
# charts fall back to PNG at roughly four times the size.
RUN php -r '$missing = array_filter(["FreeType Support", "JPEG Support", "PNG Support", "WebP Support"], fn ($k) => empty(gd_info()[$k])); if ($missing) { fwrite(STDERR, "gd built without: " . implode(", ", $missing) . PHP_EOL); exit(1); } echo "gd: freetype, jpeg, png and webp all present" . PHP_EOL;'

# Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# App code (excluding ignored paths)
COPY . .

# Built frontend assets from stage 1
COPY --from=frontend /app/public/build ./public/build

# Composer install (no dev, no scripts that need DB)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

RUN chmod +x /var/www/html/docker/entrypoint.sh

# Keep a copy of the migrations somewhere no volume can be mounted over.
#
# Installs created before the data volume moved still mount it at
# /var/www/html/database, which covers database/migrations. Docker only seeds a
# named volume from the image when the volume is first created, so that
# directory is frozen at whatever shipped the day it was made and every later
# image pull runs new code against old migrations. The entrypoint migrates from
# this copy instead, which nothing can shadow.
RUN mkdir -p /opt/weathernode \
    && cp -R /var/www/html/database/migrations /opt/weathernode/migrations

# Create writable dirs (discovery runs at runtime with .env)
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Nginx: document root = public
RUN echo 'server { \
    listen 80; \
    root /var/www/html/public; \
    index index.php; \
    server_name _; \
    charset utf-8; \
    location / { try_files $uri $uri/ /index.php?$query_string; } \
    location = /favicon.ico { access_log off; log_not_found off; } \
    location = /robots.txt { access_log off; log_not_found off; } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; \
        include fastcgi_params; \
        fastcgi_hide_header X-Powered-By; \
    } \
    location ~ /\.(?!well-known).* { deny all; } \
}' > /etc/nginx/sites-available/default

# Supervisord: nginx + php-fpm
RUN echo '[supervisord]\n\
nodaemon=true\n\
\n\
[program:php-fpm]\n\
command=php-fpm\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n\
\n\
[program:nginx]\n\
command=nginx -g "daemon off;"\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n' > /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]

# Default process. The entrypoint exec "$@", so overriding this (or passing a
# command to docker run) runs that instead of the service manager.
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
