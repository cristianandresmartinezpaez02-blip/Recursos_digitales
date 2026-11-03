# Imagen para publicar CriptoAnálisis en internet (Render, Railway, Fly.io o cualquier
# servidor con Docker). Guía paso a paso: README.md
FROM php:8.5-apache

# unzip: lo usa Composer. pdo_pgsql (+ libpq-dev): base PostgreSQL en la nube (DATABASE_URL).
# pdo_mysql: por si algún día se usa MySQL. opcache: PHP guarda el código ya compilado en memoria
# (el servidor gratis es muy lento: así no recompila los archivos en cada visita).
RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip libpq-dev \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql pdo_pgsql opcache \
 # Dentro del contenedor los archivos no cambian: no hace falta revisarlos en cada visita.
 && printf 'opcache.enable=1\nopcache.memory_consumption=64\nopcache.max_accelerated_files=4000\nopcache.validate_timestamps=0\nrealpath_cache_ttl=600\n' > "$PHP_INI_DIR/conf.d/opcache.ini" \
 # Pocos procesos de Apache: el plan gratis tiene 0,1 CPU y cada proceso mantiene su conexión
 # persistente con Supabase (el plan gratis admite ~15).
 && printf '<IfModule mpm_prefork_module>\n  StartServers 2\n  MinSpareServers 2\n  MaxSpareServers 4\n  MaxRequestWorkers 8\n</IfModule>\n' > /etc/apache2/conf-enabled/procesos.conf \
 && a2enmod rewrite headers \
 # Los .htaccess del proyecto bloquean data/, config/, includes/…: Apache debe leerlos.
 && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 # El servicio en la nube indica el puerto en la variable PORT (Render usa 10000).
 && sed -ri 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 # El https lo pone el proxy de la nube: así PHP sabe que la conexión es segura (cookies "secure").
 && echo 'SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on' > /etc/apache2/conf-enabled/proxy-https.conf \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && echo 'date.timezone = America/Bogota' > "$PHP_INI_DIR/conf.d/zona.ini" \
 # Seguridad: no anunciar versiones de Apache/PHP, peticiones de como mucho 3 MB (el progreso
 # de los juegos es lo más grande que se envía) y cortar a quien envía las cabeceras muy despacio
 # (slowloris: con 8 procesos bastaría con 8 conexiones lentas para tumbar la página).
 && printf 'expose_php = Off\npost_max_size = 3M\nupload_max_filesize = 1M\nmax_execution_time = 30\n' > "$PHP_INI_DIR/conf.d/seguridad.ini" \
 && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\nLimitRequestBody 3145728\nTimeout 30\nKeepAliveTimeout 3\n' > /etc/apache2/conf-enabled/zz-seguridad.conf \
 && a2enmod reqtimeout

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
