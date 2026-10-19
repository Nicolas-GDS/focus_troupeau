# Image applicative : php-fpm (service app), réutilisée par queue-worker et scheduler.
FROM php:8.3-fpm-bookworm

ARG UID=1000
ARG GID=1000

# Dépendances système :
# - libpq (PostgreSQL), libicu (intl), libzip (zip)
# - python3 + poppler-utils (pdftotext) : le job d'extraction des bulletins LANO
#   appelle parse_lano.py via Symfony\Process depuis le worker (chapitre 6.1)
# - chromium + nodejs/npm : rendu des PDF à la charte par Browsershot (chapitre 6.1, EF-GEN-08, lot L3) ;
#   fonts-liberation et fonts-dejavu-core en secours des polices auto-hébergées
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev \
        python3 poppler-utils \
        chromium nodejs npm fonts-liberation fonts-dejavu-core \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip bcmath pcntl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Puppeteer, piloté par Browsershot, avec le Chromium du système (pas de téléchargement de navigateur)
ENV PUPPETEER_SKIP_DOWNLOAD=1 \
    BROWSERSHOT_CHROME_PATH=/usr/bin/chromium \
    BROWSERSHOT_NODE_MODULES=/usr/local/lib/node_modules
RUN npm install -g puppeteer@23.11.1 && npm cache clean --force

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-focustroupeau.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-focustroupeau.conf
ENV PHP_FPM_MAX_CHILDREN=20

# Exécution sous l'UID de l'hôte pour éviter les fichiers root dans le dépôt monté
RUN groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" -g "${GID}" www-data

WORKDIR /var/www/html
# Configuration des outils CLI (tinker / psysh, composer) hors du dossier personnel non inscriptible
ENV XDG_CONFIG_HOME=/tmp/.config COMPOSER_HOME=/tmp/.composer
USER www-data
