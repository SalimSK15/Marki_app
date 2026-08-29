#!/usr/bin/env bash
# ==============================================================================
# Script d'importation de la base de données markii_db pour MARKIApp
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SQL_FILE="${PROJECT_ROOT}/database/markii_db.sql"

DB_HOST="${MARKI_DB_HOST:-127.0.0.1}"
DB_PORT="${MARKI_DB_PORT:-3307}"
DB_NAME="${MARKI_DB_NAME:-markii_db}"
DB_USER="${MARKI_DB_USER:-root}"

echo "========================================================"
echo " Importation de ${SQL_FILE}"
echo " Cible : ${DB_USER}@${DB_HOST}:${DB_PORT} / Base : ${DB_NAME}"
echo "========================================================"

if [ ! -f "${SQL_FILE}" ]; then
    echo "Erreur : Le fichier SQL '${SQL_FILE}' n'existe pas." >&2
    exit 1
fi

echo "1. Création de la base de données '${DB_NAME}' si elle n'existe pas..."
mysql -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -p -e \
    "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "2. Importation des tables et données..."
mysql -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -p "${DB_NAME}" < "${SQL_FILE}"

echo "========================================================"
echo " Importation terminée avec succès !"
echo "========================================================"
