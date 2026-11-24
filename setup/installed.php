<?php

/**
 * Cek apakah Drupal sudah terpasang.
 *
 * Kode keluar:
 *   0 = sudah terpasang
 *   1 = database terjangkau tetapi kosong
 *   2 = database tidak terjangkau
 *
 * modules.sh memakai ini supaya site:install (yang menghapus tabel) hanya
 * jalan pada database kosong.
 */

$host = getenv('KH_DB_HOST');
if (!$host) {
  $host = 'db';
}

$table = getenv('KH_TABLE');
if (!$table) {
  $table = 'key_value';
}

try {
  $pdo = new PDO(
    'mysql:host=' . $host . ';dbname=drupal',
    'drupal',
    'drupal',
    [
      PDO::ATTR_TIMEOUT => 3,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
  );
}
catch (\Throwable $e) {
  fwrite(STDERR, 'DB tidak terjangkau: ' . $e->getMessage() . "\n");
  exit(2);
}

$query = $pdo->prepare(
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'drupal' AND table_name = ?"
);
$query->execute([$table]);

if ($query->fetchColumn() > 0) {
  exit(0);
}
exit(1);
