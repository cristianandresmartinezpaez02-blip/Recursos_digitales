# Imagen para publicar CriptoAnálisis en internet (Render, Railway, Fly.io o cualquier
# servidor con Docker). Guía paso a paso: PUBLICAR_EN_GITHUB.md
FROM php:8.3-apache

# unzip: lo usa Composer. pdo_pgsql (+ libpq-dev): base PostgreSQL en la nube (DATABASE_URL).
# pdo_mysql: por si algún día se usa MySQL. (curl, openssl, mbstring y pdo_sqlite ya vienen.)
RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip libpq-dev \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql pdo_pgsql \
 && a2enmod rewrite headers \
 # Los .htaccess del proyecto bloquean data/, config/, includes/…: Apache debe leerlos.
 && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 # El servicio en la nube indica el puerto en la variable PORT (Render usa 10000).
 && sed -ri 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 # El https lo pone el proxy de la nube: así PHP sabe que la conexión es segura (cookies "secure").
 && echo 'SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on' > /etc/apache2/conf-enabled/proxy-https.conf \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && echo 'date.timezone = America/Bogota' > "$PHP_INI_DIR/conf.d/zona.ini"

ENV PORT=80
WORKDIR /var/www/html

# SDK oficial de Anthropic (solo lo usa el botón opcional "Explícamelo con IA (Claude)").
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY . .
# La base de datos SQLite y la caché se crean solas en data/ la primera vez.
RUN mkdir -p data/cache && chown -R www-data:www-data data

EXPOSE 80
