<?php

/**
 * Vocabulary terkontrol dan term awal Knowledge Hub. Aman diulang.
 */

// id vocabulary => [nama, daftar term]
$vocabularies = [
  'departemen' => [
    'Departemen',
    ['HR', 'Finance', 'IT', 'Operations'],
  ],
  'audience' => [
    'Audience',
    ['Pegawai Baru', 'Pegawai Lama', 'Supervisor', 'SME'],
  ],
  'topik' => [
    'Topik',
    [
      'Cuti',
      'Keselamatan Kerja',
      'Kinerja',
      'Komunikasi',
      'Pelaporan',
      'Penggunaan Sistem',
      'Perjalanan Dinas',
      'Rekrutmen',
    ],
  ],
  'difficulty' => [
    'Difficulty',
    ['Dasar', 'Menengah', 'Lanjutan'],
  ],
  'jenis_prosedur' => [
    'Jenis Prosedur',
    ['SOP', 'Panduan Kerja', 'Checklist'],
  ],
  'jenis_referensi' => [
    'Jenis Referensi',
    ['Knowledge Article', 'Kebijakan', 'Glossary', 'Studi Kasus'],
  ],
  'jenis_pembelajaran' => [
    'Jenis Pembelajaran',
    ['Tutorial', 'Video Tutorial'],
  ],
  'jenis_bantuan' => [
    'Jenis Bantuan',
    ['FAQ', 'Troubleshooting'],
  ],
  'jenis_alat_kerja' => [
    'Jenis Alat Kerja',
    ['Template'],
  ],
];

$entity_manager = \Drupal::entityTypeManager();
$vocabulary_storage = $entity_manager->getStorage('taxonomy_vocabulary');
$term_storage = $entity_manager->getStorage('taxonomy_term');

foreach ($vocabularies as $vocabulary_id => $definition) {
  list($label, $term_names) = $definition;

  if (!$vocabulary_storage->load($vocabulary_id)) {
    $vocabulary_storage->create(['vid' => $vocabulary_id, 'name' => $label])->save();
  }

  foreach ($term_names as $weight => $term_name) {
    $existing = $term_storage->loadByProperties(['vid' => $vocabulary_id, 'name' => $term_name]);
    if (empty($existing)) {
      $term_storage->create([
        'vid' => $vocabulary_id,
        'name' => $term_name,
        'weight' => $weight,
      ])->save();
    }
  }
}

echo "seed_terms ok\n";
