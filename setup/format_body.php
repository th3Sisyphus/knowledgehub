<?php

/**
 * Tulis ulang isi semua node seed dengan bentuk per jenis dari catalog.json
 * (daftar bernomor, heading FAQ, dan seterusnya).
 *
 * Hanya field body. Tidak membuat revisi baru dan tidak mengubah status moderasi.
 */

require_once __DIR__ . '/body.php';

$nodes = \Drupal::entityTypeManager()->getStorage('node');
$catalog = json_decode(file_get_contents(__DIR__ . '/catalog.json'), TRUE);

$updated = 0;
foreach ($catalog as $entry) {
  $found = $nodes->loadByProperties(['field_slug_asal' => $entry['id']]);
  if (empty($found)) {
    continue;
  }

  $node = reset($found);
  $node->set('body', ['value' => kh_body($entry), 'format' => 'basic_html']);
  $node->setNewRevision(FALSE);
  $node->save();
  $updated++;
}

echo "format_body ok: $updated node\n";
