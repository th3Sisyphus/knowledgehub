<?php

/**
 * Cek hasil impor: jumlah node per content type cocok dengan catalog.json,
 * dan beberapa node contoh punya nilai yang benar.
 * Mencetak OK bila lulus, selain itu daftar masalah.
 */

require_once __DIR__ . '/helpers.php';

// Grup di catalog => content type di Drupal.
$bundle_of_group = [
  'prosedur' => 'prosedur',
  'referensi' => 'referensi',
  'pembelajaran' => 'pembelajaran',
  'bantuan' => 'bantuan',
  'alat-kerja' => 'alat_kerja',
];

$catalog = json_decode(file_get_contents('/opt/drupal/setup/catalog.json'), TRUE);

// Hitung berapa entri per content type di catalog.
$want = [];
foreach ($catalog as $entry) {
  $bundle = $bundle_of_group[$entry['group']];
  if (!isset($want[$bundle])) {
    $want[$bundle] = 0;
  }
  $want[$bundle]++;
}

$bad = [];

foreach ($want as $bundle => $count) {
  $query = \Drupal::entityQuery('node');
  $query->accessCheck(FALSE);
  $query->condition('type', $bundle);
  $got = (int) $query->count()->execute();

  if ($got !== $count) {
    $bad[] = $bundle . ': harus ' . $count . ', ada ' . $got;
  }
}

// Node contoh: SOP Perjalanan Dinas harus HR dan berjenis SOP.
$sop = kh_check_node('sop-perjalanan-dinas');
if (!$sop) {
  $bad[] = 'sop-perjalanan-dinas tidak ada';
}
elseif ($sop->get('field_departemen')->entity->label() !== 'HR') {
  $bad[] = 'departemen sop-perjalanan-dinas bukan HR';
}
elseif ($sop->get('field_jenis')->entity->label() !== 'SOP') {
  $bad[] = 'jenis sop-perjalanan-dinas bukan SOP';
}

// SOP Lembur berstatus Draft: harus ada dan tidak terbit.
$lembur = kh_check_node('sop-lembur');
if (!$lembur || $lembur->isPublished()) {
  $bad[] = 'sop-lembur harus ada dan tidak terbit';
}

kh_check_report($bad);
