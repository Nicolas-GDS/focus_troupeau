# Image de production (chapitre 6.5, ENF-EXP-01) : versionnée par son étiquette (version sémantique), sans dépendance
# de développement, ressources front compilées, caches Laravel construits au démarrage, OPcache sans vérification des
# fichiers. Construite depuis la racine du dépôt :
#   docker build -f docker/production/Dockerfile --build-arg VERSION=1.0.0 -t focustroupeau/app:1.0.0 .
#   (dernière étape) ; le serveur web : --target web.

# 1. Ressources front (Vite, Tailwind)
FROM node:22-alpine AS front
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
RUN npm run build

# 2. Dépendances PHP sans outils de développement
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# 3. Serveur web (nginx) : fichiers publics et ressources compilées ; PHP est servi par le conteneur « app »
#   docker build -f docker/production/Dockerfile --target web -t focustroupeau/web:1.0.0 .
FROM nginx:1.27-alpine AS web
COPY docker/production/nginx.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=front /app/public/build /var/www/html/public/build

# 4. Image d'exécution : php-fpm, extraction des bulletins (python3, pdftotext), rendu PDF (Chromium, Puppeteer)
FROM php:8.3-fpm-bookworm
ARG VERSION=0.0.0
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libicu-dev libzip-dev python3 poppler-utils chromium nodejs npm fonts-liberation fonts-dejavu-core \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip bcmath pcntl opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
ENV PUPPETEER_SKIP_DOWNLOAD=1 \
    BROWSERSHOT_CHROME_PATH=/usr/bin/chromium \
    BROWSERSHOT_NODE_MODULES=/usr/local/lib/node_modules \
    PHP_FPM_MAX_CHILDREN=20 \
    APP_VERSION=${VERSION}
RUN npm install -g puppeteer@23.11.1 && npm cache clean --force

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-focustroupeau.ini
COPY docker/production/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-focustroupeau.conf
COPY docker/production/demarrage.sh /usr/local/bin/demarrage

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=front --chown=www-data:www-data /app/public/build ./public/build
RUN rm -rf tests docs/recette database/seeders/valider_seeders.py \
    && mkdir -p storage/app/private storage/app/sauvegardes storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache && chmod +x /usr/local/bin/demarrage \
    && chown www-data:www-data /var/www
# /var/www : répertoire personnel de www-data, où Chromium (rendu des PDF) écrit son profil

USER www-data
ENTRYPOINT ["demarrage"]
CMD ["php-fpm"]
