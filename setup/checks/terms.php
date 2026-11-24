<?php

/**
 * Cek jumlah term per vocabulary sesuai spec.
 * Mencetak OK bila semua cocok, selain itu daftar selisih.
 */

require_once __DIR__ . '/helpers.php';

// vocabulary => jumlah term yang diharapkan.
$expected = [
  'departemen' => 4,
  'audience' => 4,
  'topik' => 8,
  'difficulty' => 3,
  'jenis_prosedur' => 3,
  'jenis_referensi' => 4,
  'jenis_pembelajaran' => 2,
  'jenis_bantuan' => 2,
  'jenis_alat_kerja' => 1,
];

$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$bad = [];

foreach ($expected as $vocabulary => $want) {
  $query = $storage->getQuery();
  $query->accessCheck(FALSE);
  $query->condition('vid', $vocabulary);
  $got = (int) $query->count()->execute();

  if ($got !== $want) {
    $bad[] = $vocabulary . ': harus ' . $want . ', ada ' . $got;
  }
}

kh_check_report($bad);
