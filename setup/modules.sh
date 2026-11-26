#!/usr/bin/env bash
# Pasang Drupal dan modul. Jalankan dari folder Project_Akhir setelah `docker compose up -d`.
# Aman diulang: instalasi dilewati bila situs sudah ada.
set -euo pipefail
cd "$(dirname "$0")/.."
export PATH="$PATH:/c/Program Files/Docker/Docker/resources/bin"
export MSYS_NO_PATHCONV=1
dc() { docker compose exec -T drupal "$@"; }

dc composer require drush/drush drupal/pathauto drupal/token drupal/schema_metatag drupal/facets drupal/search_api --no-interaction

# settings.php harus ada dan memuat config_sync_directory sebelum instalasi (dibutuhkan --existing-config).
dc bash -c '[ -f web/sites/default/settings.php ] || cp web/sites/default/default.settings.php web/sites/default/settings.php'
dc php /opt/drupal/setup/sync_dir.php

# site:install menghapus tabel. Jalankan hanya bila DB terjangkau dan kosong (kode 1), bukan saat gagal (kode 2).
state=0; dc php /opt/drupal/setup/installed.php || state=$?
if [ "$state" -eq 2 ]; then echo "DB tidak terjangkau. Berhenti tanpa menyentuh data." >&2; exit 1; fi
if [ "$state" -eq 1 ]; then
  if [ -f config/core.extension.yml ]; then
    # Instalasi dari config repo: UUID objek cocok, jadi tidak perlu config:import.
    dc vendor/bin/drush site:install --existing-config --config-dir=/opt/drupal/config \
      --db-url=mysql://drupal:drupal@db/drupal \
      --account-name=admin --account-pass=admin -y
  else
    dc vendor/bin/drush site:install standard \
      --db-url=mysql://drupal:drupal@db/drupal --site-name="Knowledge Hub" \
      --account-name=admin --account-pass=admin --locale=id -y
  fi
fi

dc vendor/bin/drush en -y pathauto token schema_metatag schema_article schema_web_page schema_course facets search_api search_api_db content_moderation workflows kh_workflow kh_home kh_feedback kh_search
