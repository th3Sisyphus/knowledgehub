<?php

/**
 * Cek: node Bantuan dengan Jenis milik grup lain (SOP) harus ditolak validasi.
 * Mencetak OK bila ditolak, selain itu penjelasan masalah.
 */

require_once __DIR__ . '/helpers.php';

$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$found = $terms->loadByProperties(['vid' => 'jenis_prosedur', 'name' => 'SOP']);
$sop = reset($found);

$node = \Drupal\node\Entity\Node::create([
  'type' => 'bantuan',
  'title' => 'Uji jenis salah',
  'field_jenis' => $sop->id(),
]);

$violation_paths = [];
foreach ($node->validate() as $violation) {
  $violation_paths[] = $violation->getPropertyPath();
}

$bad = [];
$is_rejected = in_array('field_jenis.0.target_id', $violation_paths)
  || in_array('field_jenis', $violation_paths);
if (!$is_rejected) {
  $bad[] = 'Jenis salah grup lolos: ' . implode(',', $violation_paths);
}

kh_check_report($bad);
