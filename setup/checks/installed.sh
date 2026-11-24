#!/usr/bin/env bash
# setup/installed.php membedakan tiga keadaan: terpasang (0), belum terpasang (1), DB tidak terjangkau (2).
# modules.sh hanya boleh menjalankan site:install pada keadaan 1.
export PATH="$PATH:/c/Program Files/Docker/Docker/resources/bin" MSYS_NO_PATHCONV=1
run() { docker compose exec -T "$@" php /opt/drupal/setup/installed.php; }
run drupal;                                   [ $? -eq 0 ] || { echo "situs terpasang harus 0"; exit 1; }
run -e KH_TABLE=tabel_tidak_ada drupal;       [ $? -eq 1 ] || { echo "tabel hilang harus 1"; exit 1; }
run -e KH_DB_HOST=host-tidak-ada drupal;      [ $? -eq 2 ] || { echo "DB tidak terjangkau harus 2"; exit 1; }
grep -q "bootstrap" setup/modules.sh && { echo "modules.sh masih memakai cek bootstrap untuk instal"; exit 1; }
echo "terpasang=0 kosong=1 tak-terjangkau=2"
