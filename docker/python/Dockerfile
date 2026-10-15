# Conteneur Python : implémentation de référence (calculs/) et mise au point de parse_lano.py.
FROM python:3.12-slim-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends poppler-utils \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
