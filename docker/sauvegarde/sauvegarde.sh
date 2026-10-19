#!/bin/sh
# Sauvegarde quotidienne chiffrée de la base (ENF-SEC-07, chapitre 6.5) : pg_dump au format personnalisé, compressé,
# chiffré en AES-256 (clé SAUVEGARDE_CLE, jamais dans le dépôt) ; rétention SAUVEGARDE_RETENTION_JOURS (30 par défaut).
# Usage : docker compose --profile exploitation run --rm sauvegarde
set -eu
: "${SAUVEGARDE_CLE:?SAUVEGARDE_CLE absente : sauvegarde refusée (pas de sauvegarde en clair)}"
DOSSIER="${SAUVEGARDE_DOSSIER:-/sauvegardes}"
RETENTION="${SAUVEGARDE_RETENTION_JOURS:-30}"
HORODATAGE="$(date -u +%Y%m%dT%H%M%SZ)"
FICHIER="$DOSSIER/focustroupeau-$HORODATAGE.dump.gz.enc"
mkdir -p "$DOSSIER"
export PGPASSWORD="${DB_PASSWORD}"
pg_dump -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" --format=custom --no-owner \
  | gzip -9 \
  | openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -pass env:SAUVEGARDE_CLE -out "$FICHIER.partiel"
mv "$FICHIER.partiel" "$FICHIER"
sha256sum "$FICHIER" > "$FICHIER.sha256"
find "$DOSSIER" -name 'focustroupeau-*.dump.gz.enc*' -mtime +"$RETENTION" -delete
echo "Sauvegarde écrite : $FICHIER ($(du -h "$FICHIER" | cut -f1))"
