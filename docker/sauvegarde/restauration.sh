#!/bin/sh
# Restauration d'une sauvegarde chiffrée (ENF-SEC-07) dans la base RESTAURATION_BASE (par défaut une base de contrôle
# distincte : le test trimestriel ne touche jamais la base de production).
# Usage : docker compose --profile exploitation run --rm sauvegarde /restauration.sh /sauvegardes/<fichier>.dump.gz.enc
set -eu
: "${SAUVEGARDE_CLE:?SAUVEGARDE_CLE absente}"
FICHIER="${1:?Fichier de sauvegarde attendu}"
CIBLE="${RESTAURATION_BASE:-focustroupeau_restauration}"
export PGPASSWORD="${DB_PASSWORD}"
HOTE="${DB_HOST:-postgres}"
if [ -f "$FICHIER.sha256" ]; then sha256sum -c "$FICHIER.sha256"; fi
psql -h "$HOTE" -U "${DB_USERNAME}" -d postgres -v ON_ERROR_STOP=1 -c "DROP DATABASE IF EXISTS \"$CIBLE\";" -c "CREATE DATABASE \"$CIBLE\";"
psql -h "$HOTE" -U "${DB_USERNAME}" -d "$CIBLE" -v ON_ERROR_STOP=1 -c "CREATE EXTENSION IF NOT EXISTS postgis;"
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass env:SAUVEGARDE_CLE -in "$FICHIER" \
  | gunzip \
  | pg_restore -h "$HOTE" -U "${DB_USERNAME}" -d "$CIBLE" --no-owner --exit-on-error
psql -h "$HOTE" -U "${DB_USERNAME}" -d "$CIBLE" -At -c "SELECT 'élevages : ' || count(*) FROM fourrage.elevage UNION ALL SELECT 'analyses : ' || count(*) FROM fourrage.analyse UNION ALL SELECT 'migrations : ' || count(*) FROM public.migrations;"
echo "Restauration terminée dans la base $CIBLE."
