#!/bin/sh
# Démarrage d'un conteneur de production : caches de configuration, de routes, de vues et d'événements construits avec
# l'environnement réel ; migrations seulement pour le rôle « app » quand MIGRER=1 (un seul conteneur migre).
set -e
php artisan package:discover --ansi >/dev/null
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
if [ "${MIGRER:-0}" = "1" ]; then
    php artisan migrate --force
fi
exec "$@"
