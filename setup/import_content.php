<?php

/**
 * Impor konten dari setup/catalog.json (hasil export_catalog.mjs).
 *
 * Aman diulang: kunci = field_slug_asal. Node yang sudah ada dilewati,
 * jadi impor tidak menimpa edit, status moderasi, atau revisi.
 * Term yang tidak ada menghentikan skrip. Skrip tidak membuat term.
 */

use Drupal\node\Entity\Node;

require_once __DIR__ . '/body.php';

/**
 * Ambil id term dari vocabulary dan nama. Berhenti bila term tidak ada.
 */
function kh_term($vocabulary, $name) {
  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $found = $storage->loadByProperties(['vid' => $vocabulary, 'name' => $name]);
  if (empty($found)) {
    throw new \RuntimeException("Term tidak ada: $vocabulary / $name");
  }
  $term = reset($found);
  return (int) $term->id();
}

/**
 * Ambil id term untuk daftar nama.
 */
function kh_terms($vocabulary, $names) {
  $ids = [];
  foreach ($names as $name) {
    $ids[] = kh_term($vocabulary, $name);
  }
  return $ids;
}

/**
 * Apakah node di content type ini memakai Content Moderation.
 */
function kh_is_moderated($bundle) {
  if (!\Drupal::moduleHandler()->moduleExists('content_moderation')) {
    return FALSE;
  }
  $info = \Drupal::service('content_moderation.moderation_information');
  $definition = \Drupal::entityTypeManager()->getDefinition('node');
  return $info->shouldModerateEntitiesOfBundle($definition, $bundle);
}

/**
 * Susun nilai field untuk satu entri catalog.
 */
function kh_node_values($entry, $bundle, $owner) {
  $is_draft = isset($entry['status']) && $entry['status'] === 'Draft';
  $status = 1;
  $moderation_state = 'published';
  if ($is_draft) {
    $status = 0;
    $moderation_state = 'draft';
  }

  $values = [
    'type' => $bundle,
    'title' => $entry['title'],
    'uid' => $owner,
    'status' => $status,
    'field_slug_asal' => $entry['id'],
    'field_jenis' => kh_term('jenis_' . $bundle, $entry['type']),
    'field_departemen' => kh_term('departemen', $entry['dept']),
    'field_topik' => kh_term('topik', $entry['topic']),
    'field_ringkasan' => $entry['desc'],
    'field_tanggal' => $entry['date'],
    'body' => ['value' => kh_body($entry), 'format' => 'basic_html'],
  ];

  // Audience hanya ada di tiga content type.
  $bundles_with_audience = ['prosedur', 'referensi', 'pembelajaran'];
  if (in_array($bundle, $bundles_with_audience)) {
    $audience = [];
    if (isset($entry['aud'])) {
      $audience = $entry['aud'];
    }
    $values['field_audience'] = kh_terms('audience', $audience);
  }

  if ($bundle === 'pembelajaran') {
    $values['field_difficulty'] = kh_term('difficulty', $entry['diff']);
  }

  // Field opsional: nama kunci di catalog => nama field di Drupal.
  $optional = [
    'version' => 'field_versi',
    'number' => 'field_nomor',
    'callout' => 'field_callout',
    'source' => 'field_sumber',
    'duration' => 'field_durasi',
  ];
  foreach ($optional as $key => $field) {
    if (isset($entry[$key])) {
      $values[$field] = $entry[$key];
    }
  }

  if (isset($entry['wajib'])) {
    $values['field_wajib'] = (bool) $entry['wajib'];
  }

  if (kh_is_moderated($bundle)) {
    $values['moderation_state'] = $moderation_state;
  }

  return $values;
}

// Grup di catalog => content type di Drupal.
$bundle_of_group = [
  'prosedur' => 'prosedur',
  'referensi' => 'referensi',
  'pembelajaran' => 'pembelajaran',
  'bantuan' => 'bantuan',
  'alat-kerja' => 'alat_kerja',
];

$catalog = json_decode(file_get_contents('/opt/drupal/setup/catalog.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$nodes = \Drupal::entityTypeManager()->getStorage('node');

// Konten seed dimiliki demo_admin supaya Admin bisa mengeditnya.
$demo_admin = user_load_by_name('demo_admin');
$owner = 1;
if ($demo_admin) {
  $owner = (int) $demo_admin->id();
}

$created = 0;
$skipped = 0;
foreach ($catalog as $entry) {
  $exists = $nodes->loadByProperties(['field_slug_asal' => $entry['id']]);
  if (!empty($exists)) {
    $skipped++;
    continue;
  }

  $bundle = $bundle_of_group[$entry['group']];
  $node = Node::create(kh_node_values($entry, $bundle, $owner));
  $node->save();
  $created++;
}

echo "import ok: $created baru, $skipped dilewati\n";
