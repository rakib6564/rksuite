#!/usr/bin/env bash
# Build a clean, installable WordPress plugin zip at dist/rk-suite.zip.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="${ROOT_DIR}/rk-suite"
DIST_DIR="${ROOT_DIR}/dist"
SLUG="rk-suite"
STAGE_DIR="$(mktemp -d)"

cleanup() {
  rm -rf "${STAGE_DIR}"
}
trap cleanup EXIT

if [[ ! -f "${PLUGIN_DIR}/rk-suite.php" ]]; then
  echo "Error: plugin entry file not found at ${PLUGIN_DIR}/rk-suite.php" >&2
  exit 1
fi

rm -rf "${DIST_DIR}"
mkdir -p "${DIST_DIR}" "${STAGE_DIR}/${SLUG}"

tar -C "${PLUGIN_DIR}" -cf - \
  --exclude='./tests' \
  --exclude='./vendor' \
  --exclude='./node_modules' \
  --exclude='./dist' \
  --exclude='./.git' \
  --exclude='./.github' \
  --exclude='./composer.json' \
  --exclude='./composer.lock' \
  --exclude='./phpunit.xml' \
  --exclude='./phpcs.xml*' \
  --exclude='*.md' \
  --exclude='./AUDIT-*' \
  . | tar -C "${STAGE_DIR}/${SLUG}" -xf -

(
  cd "${STAGE_DIR}"
  zip -rqX "${DIST_DIR}/${SLUG}.zip" "${SLUG}"
)

echo "Built ${DIST_DIR}/${SLUG}.zip"
unzip -l "${DIST_DIR}/${SLUG}.zip" | tail -1
