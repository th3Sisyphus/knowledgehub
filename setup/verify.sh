#!/usr/bin/env bash
# Pemeriksaan keseluruhan Project Akhir. Jalankan dari folder Project_Akhir: bash setup/verify.sh
cd "$(dirname "$0")/.." || exit 1
export PATH="$PATH:/c/Program Files/Docker/Docker/resources/bin"
FAIL=0
BASE=${BASE_URL:-http://127.0.0.1:8080}
export MSYS_NO_PATHCONV=1
drush() { docker compose exec -T drupal vendor/bin/drush "$@"; }
check() { # check <nama> <perintah...>
  local name=$1; shift
  if out=$("$@" 2>&1); then echo "PASS $name"; else echo "FAIL $name"; echo "$out" | tail -n 3 | sed 's/^/     /'; FAIL=1; fi
}
http_is() { [ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE$1")" = "$2" ]; }
modules_enabled() { local l; l=$(drush pm:list --status=enabled --field=name) || return 1; for m in "$@"; do echo "$l" | grep -qx "$m" || { echo "modul mati: $m"; return 1; }; done; }

# ---- Task 1
check "situs menjawab 200" http_is / 200
check "modul aktif" modules_enabled pathauto token schema_metatag facets search_api search_api_db content_moderation workflows

# ---- Task 2
# Skrip checks mencetak OK saat lulus. Kode exit drush tidak dipakai (drush menandai exit non-nol sebagai error).
php_check() { local o; o=$(drush php:script "/opt/drupal/setup/checks/$1.php" 2>&1); echo "$o"; [ "$(echo "$o" | tail -n 1)" = OK ]; }
check "jumlah term per vocabulary" php_check terms

# ---- Task 3
check "content type, field wajib, Jenis per grup" php_check model
check "Jenis dari grup lain ditolak" php_check jenis_salah

# ---- Task 4
check "impor: jumlah node per tipe cocok catalog.json" php_check import

# ---- Task 5
check "role, workflow, akses, form Auditor" php_check roles
check "anonim: Draft 403, Published 200" bash setup/checks/anon.sh

# ---- Task 6
check "alias, meta description, JSON-LD, alias kembar" php_check seo

# ---- Task 7
check "pencarian, facet, menu, konten terkait" php_check discovery

# ---- Review akhir
check "review akhir: impor ulang, alias, akses Admin, kueri pendek, navigasi" php_check final
check "tema: beranda, daftar, halaman konten mirip prototipe" php_check theme
check "deteksi instalasi tidak menghapus DB yang rusak" bash setup/checks/installed.sh
check "port hanya localhost" bash setup/checks/ports.sh

exit $FAIL
