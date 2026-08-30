#!/usr/bin/env bash
set -euo pipefail

umask 077

BACKUP_DIR="${BACKUP_DIR:-/backups/narlit}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:?DB_NAME is required}"
DB_USERNAME="${DB_USERNAME:?DB_USERNAME is required}"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BASE_NAME="narlit-postgres-${TIMESTAMP}.dump"
TMP_FILE="${BACKUP_DIR}/.${BASE_NAME}.tmp"
FINAL_FILE="${BACKUP_DIR}/${BASE_NAME}"
LOCK_FILE="${BACKUP_DIR}/.narlit-postgres-backup.lock"

mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

exec 9>"${LOCK_FILE}"
flock -n 9

cleanup() {
    rm -f "${TMP_FILE}"
}
trap cleanup EXIT

echo "Starting NarLit PostgreSQL backup ${BASE_NAME}"

pg_dump \
    --format=custom \
    --no-owner \
    --no-acl \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --username="${DB_USERNAME}" \
    --dbname="${DB_NAME}" \
    --file="${TMP_FILE}"

pg_restore --list "${TMP_FILE}" >/dev/null

chmod 600 "${TMP_FILE}"
mv "${TMP_FILE}" "${FINAL_FILE}"
trap - EXIT

BACKUP_SIZE="$(wc -c < "${FINAL_FILE}")"
echo "Completed NarLit PostgreSQL backup ${FINAL_FILE} (${BACKUP_SIZE} bytes)"

find "${BACKUP_DIR}" \
    -maxdepth 1 \
    -type f \
    -name 'narlit-postgres-*.dump' \
    -mtime +"${RETENTION_DAYS}" \
    -delete

echo "Applied local retention policy in ${BACKUP_DIR} for narlit-postgres-*.dump older than ${RETENTION_DAYS} days"
