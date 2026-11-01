#!/usr/bin/env bash
#
# Build a wordpress.org-ready plugin zip.
#
# 1. Copies the working tree to a temporary staging directory.
# 2. Installs production Composer dependencies there (no dev packages).
# 3. Copies the result into build/wp-migrate-db-anonymization/, skipping
#    everything listed in .distignore.
# 4. Zips it as build/wp-migrate-db-anonymization.zip.
#
# Usage: bin/build.sh            (or: composer build)
# Env:   COMPOSER_BIN=/path/to/composer to use a specific Composer binary.
#        PHP_BIN=/path/to/php to run Composer with a specific PHP binary, so
#        dependencies are resolved for that PHP version.

set -euo pipefail

SLUG="wp-migrate-db-anonymization"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${ROOT}/build"
ZIP_FILE="${BUILD_DIR}/${SLUG}.zip"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
PHP_BIN="${PHP_BIN:-}"

for cmd in rsync zip "${COMPOSER_BIN}"; do
	if ! command -v "${cmd}" > /dev/null 2>&1; then
		echo "Error: '${cmd}' is required but was not found on PATH." >&2
		exit 1
	fi
done

if [ ! -f "${ROOT}/.distignore" ]; then
	echo "Error: ${ROOT}/.distignore not found." >&2
	exit 1
fi

STAGING_DIR="$(mktemp -d "${TMPDIR:-/tmp}/${SLUG}-build.XXXXXX")"
trap 'rm -rf "${STAGING_DIR}"' EXIT

echo "Staging source in ${STAGING_DIR}"
rsync -a \
	--exclude='/.git' \
	--exclude='/vendor' \
	--exclude='/build' \
	"${ROOT}/" "${STAGING_DIR}/"

echo "Installing production dependencies"
(
	cd "${STAGING_DIR}"
	if [ -n "${PHP_BIN}" ]; then
		"${PHP_BIN}" "$(command -v "${COMPOSER_BIN}")" install --no-dev --optimize-autoloader --no-interaction --no-progress
	else
		"${COMPOSER_BIN}" install --no-dev --optimize-autoloader --no-interaction --no-progress
	fi
)

echo "Applying .distignore"
rm -rf "${BUILD_DIR:?}/${SLUG}" "${ZIP_FILE}"
mkdir -p "${BUILD_DIR}/${SLUG}"
rsync -a --exclude-from="${ROOT}/.distignore" "${STAGING_DIR}/" "${BUILD_DIR}/${SLUG}/"

echo "Creating ${ZIP_FILE}"
(
	cd "${BUILD_DIR}"
	zip -rq "${SLUG}.zip" "${SLUG}"
)

echo "Done: ${ZIP_FILE}"
