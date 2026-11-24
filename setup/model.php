<?php

/**
 * Content type, field, form display, dan view display Knowledge Hub.
 * Aman diulang.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

// Content type => nama tampilan.
$content_types = [
  'prosedur' => 'Prosedur',
  'referensi' => 'Referensi',
  'pembelajaran' => 'Pembelajaran',
  'bantuan' => 'Bantuan',
  'alat_kerja' => 'Alat Kerja',
];

/**
 * Buat content type bila belum ada, lengkap dengan form dan view display.
 */
function kh_create_content_type($bundle, $label) {
  if (!NodeType::load($bundle)) {
    NodeType::create([
      'type' => $bundle,
      'name' => $label,
      'description' => 'Konten grup ' . $label . '.',
      'new_revision' => TRUE,
      'display_submitted' => FALSE,
    ])->save();
  }

  $display_classes = [EntityFormDisplay::class, EntityViewDisplay::class];
  foreach ($display_classes as $display_class) {
    if (!$display_class::load('node.' . $bundle . '.default')) {
      $display_class::create([
        'targetEntityType' => 'node',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ])->save();
    }
  }
}

/**
 * Tambah satu field ke content type, termasuk tampilan di form dan di halaman.
 *
 * Pengaturan yang tidak diisi memakai nilai bawaan di bawah ini.
 */
function kh_add_field($bundle, $name, $type, $label, $options = []) {
  $defaults = [
    'required' => FALSE,
    'cardinality' => 1,
    'description' => '',
    'weight' => 10,
    'storage_settings' => [],
    'field_settings' => [],
    'widget' => 'string_textfield',
    'widget_settings' => [],
    'formatter' => 'string',
    'formatter_settings' => [],
    'label_display' => 'above',
  ];
  $options = $options + $defaults;

  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $type,
      'cardinality' => $options['cardinality'],
      'settings' => $options['storage_settings'],
    ])->save();
  }

  if (!FieldConfig::loadByName('node', $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $label,
      'required' => $options['required'],
      'description' => $options['description'],
      'settings' => $options['field_settings'],
    ])->save();
  }

  $form_display = EntityFormDisplay::load('node.' . $bundle . '.default');
  $form_display->setComponent($name, [
    'type' => $options['widget'],
    'weight' => $options['weight'],
    'settings' => $options['widget_settings'],
  ]);
  $form_display->save();

  $view_display = EntityViewDisplay::load('node.' . $bundle . '.default');
  $view_display->setComponent($name, [
    'type' => $options['formatter'],
    'label' => $options['label_display'],
    'weight' => $options['weight'],
    'settings' => $options['formatter_settings'],
  ]);
  $view_display->save();
}

/**
 * Tambah field referensi ke satu vocabulary terkontrol.
 *
 * Admin hanya bisa memilih term yang sudah ada (auto_create mati).
 */
function kh_add_term_field($bundle, $name, $label, $vocabulary, $options = []) {
  $reference_defaults = [
    'widget' => 'options_select',
    'formatter' => 'entity_reference_label',
    'formatter_settings' => ['link' => TRUE],
    'storage_settings' => ['target_type' => 'taxonomy_term'],
  ];
  $options = $options + $reference_defaults;

  $options['field_settings'] = [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => [
      'target_bundles' => [$vocabulary => $vocabulary],
      'auto_create' => FALSE,
      'sort' => ['field' => 'name', 'direction' => 'asc'],
    ],
  ];

  kh_add_field($bundle, $name, 'entity_reference', $label, $options);
}

/**
 * Field yang ada di semua content type.
 */
function kh_add_shared_fields($bundle, $label) {
  kh_add_field($bundle, 'body', 'text_with_summary', 'Isi', [
    'weight' => 10,
    'widget' => 'text_textarea_with_summary',
    'formatter' => 'text_default',
    'label_display' => 'hidden',
    'field_settings' => ['display_summary' => FALSE],
  ]);

  // Jenis dibatasi ke vocabulary milik grup ini.
  kh_add_term_field($bundle, 'field_jenis', 'Jenis', 'jenis_' . $bundle, [
    'required' => TRUE,
    'weight' => 1,
    'description' => 'Hanya jenis milik grup ' . $label . '.',
  ]);
  kh_add_term_field($bundle, 'field_departemen', 'Departemen', 'departemen', [
    'required' => TRUE,
    'weight' => 2,
  ]);
  kh_add_term_field($bundle, 'field_topik', 'Topik', 'topik', [
    'required' => TRUE,
    'weight' => 3,
  ]);

  kh_add_field($bundle, 'field_ringkasan', 'string', 'Ringkasan', [
    'required' => TRUE,
    'weight' => 4,
    'storage_settings' => ['max_length' => 160],
    'description' => 'Satu kalimat, maksimal 160 karakter. Dipakai sebagai meta description.',
  ]);

  kh_add_field($bundle, 'field_tanggal', 'datetime', 'Tanggal', [
    'weight' => 20,
    'storage_settings' => ['datetime_type' => 'date'],
    'widget' => 'datetime_default',
    'formatter' => 'datetime_default',
    'formatter_settings' => ['format_type' => 'medium'],
  ]);

  kh_add_field($bundle, 'field_slug_asal', 'string', 'ID asal (prototipe)', [
    'weight' => 40,
    'description' => 'Penanda impor dari prototipe. Jangan diubah.',
  ]);
}

/**
 * Field khusus: hanya ada di sebagian content type, mengikuti kolom di data.js.
 */
function kh_add_extra_fields() {
  foreach (['prosedur', 'referensi', 'pembelajaran'] as $bundle) {
    kh_add_term_field($bundle, 'field_audience', 'Audience', 'audience', [
      'cardinality' => -1,
      'weight' => 5,
      'widget' => 'options_buttons',
    ]);
    kh_add_field($bundle, 'field_sumber', 'string', 'Kode sumber (inventaris)', [
      'weight' => 25,
      'storage_settings' => ['max_length' => 32],
      'description' => 'Kode dari inventaris konten Milestone 2, contoh C03.',
    ]);
  }

  foreach (['prosedur', 'referensi', 'alat_kerja'] as $bundle) {
    kh_add_field($bundle, 'field_versi', 'string', 'Versi', [
      'weight' => 21,
      'storage_settings' => ['max_length' => 20],
    ]);
  }

  foreach (['prosedur', 'referensi'] as $bundle) {
    kh_add_field($bundle, 'field_wajib', 'boolean', 'Wajib dipatuhi', [
      'weight' => 23,
      'widget' => 'boolean_checkbox',
      'widget_settings' => ['display_label' => TRUE],
      'formatter' => 'boolean',
    ]);
  }

  foreach (['referensi', 'bantuan'] as $bundle) {
    kh_add_field($bundle, 'field_callout', 'string_long', 'Callout', [
      'weight' => 24,
      'widget' => 'string_textarea',
      'widget_settings' => ['rows' => 3],
      'formatter' => 'basic_string',
    ]);
  }

  kh_add_term_field('pembelajaran', 'field_difficulty', 'Difficulty', 'difficulty', [
    'weight' => 6,
  ]);

  kh_add_field('referensi', 'field_nomor', 'string', 'Nomor dokumen', [
    'weight' => 22,
    'storage_settings' => ['max_length' => 64],
  ]);

  kh_add_field('pembelajaran', 'field_durasi', 'integer', 'Durasi (detik)', [
    'weight' => 26,
    'widget' => 'number',
    'formatter' => 'number_integer',
    'field_settings' => ['min' => 1],
  ]);
}

foreach ($content_types as $bundle => $label) {
  kh_create_content_type($bundle, $label);
  kh_add_shared_fields($bundle, $label);
}
kh_add_extra_fields();

drupal_flush_all_caches();
echo "model ok\n";
