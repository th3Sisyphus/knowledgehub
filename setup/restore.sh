#!/usr/bin/env bash
# Pulihkan Knowledge Hub dari repo pada instalasi bersih. Jalankan setelah `docker compose up -d`.
# Urutan: instal dari config repo (modules.sh), term, role, konten, alias, indeks pencarian.
set -euo pipefail
cd "$(dirname "$0")/.."
export PATH="$PATH:/c/Program Files/Docker/Docker/resources/bin"
export MSYS_NO_PATHCONV=1
drush() { docker compose exec -T drupal vendor/bin/drush "$@"; }

bash setup/modules.sh
# catalog.json sudah ada di repo. Buat ulang hanya bila Node dan prototipe tersedia.
if [ -f ../Mock_Web_Knowledge_Hub/data.js ] && command -v node >/dev/null 2>&1; then
  node setup/export_catalog.mjs
fi
drush php:script /opt/drupal/setup/seed_terms.php
drush php:script /opt/drupal/setup/roles.php
drush php:script /opt/drupal/setup/import_content.php
drush php:script /opt/drupal/setup/discovery.php
drush php:script /opt/drupal/setup/search.php
drush php:script /opt/drupal/setup/theme.php
echo "restore selesai"
