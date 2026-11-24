#!/usr/bin/env bash
# Anonim: Draft 403, Published 200. Dipanggil dari verify.sh dengan BASE dan fungsi drush.
set -u
export MSYS_NO_PATHCONV=1
BASE=${BASE_URL:-http://127.0.0.1:8080}
nid_of() {
  docker compose exec -T drupal vendor/bin/drush php:eval \
    'echo reset(\Drupal::entityTypeManager()->getStorage("node")->loadByProperties(["field_slug_asal" => "'"$1"'"]))->id();'
}
code() { curl -s -o /dev/null -w '%{http_code}' "$BASE$1"; }
d=$(code "/node/$(nid_of sop-lembur)")
p=$(code "/node/$(nid_of sop-perjalanan-dinas)")
[ "$d" = 403 ] || { echo "Draft anonim: $d (harus 403)"; exit 1; }
[ "$p" = 200 ] || { echo "Published anonim: $p (harus 200)"; exit 1; }
echo "draft=403 published=200"
