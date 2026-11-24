<?php

/**
 * Cek struktur content type sesuai spec: field wajib, Jenis terkunci per grup,
 * field khusus hanya di content type yang memakainya, dan validasi Ringkasan.
 * Mencetak OK bila lulus, selain itu daftar masalah.
 */

require_once __DIR__ . '/helpers.php';

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

const KH_CHECK_BUNDLES = ['prosedur', 'referensi', 'pembelajaran', 'bantuan', 'alat_kerja'];

$bad = [];

/**
 * Target vocabulary sebuah field referensi, contoh ['jenis_bantuan'].
 */
function kh_check_target_vocabularies($field_config) {
  $handler_settings = $field_config->getSetting('handler_settings');
  if (!isset($handler_settings['target_bundles'])) {
    return [];
  }
  return array_keys($handler_settings['target_bundles']);
}

/**
 * Apakah field referensi ini boleh membuat term baru dari form.
 */
function kh_check_allows_auto_create($field_config) {
  $handler_settings = $field_config->getSetting('handler_settings');
  return !empty($handler_settings['auto_create']);
}

/**
 * Field wajib ada dan wajib diisi di tiap content type.
 */
function kh_check_required_fields($bundle, &$bad) {
  $required_fields = ['field_jenis', 'field_departemen', 'field_topik', 'field_ringkasan'];

  foreach ($required_fields as $field_name) {
    $field = FieldConfig::loadByName('node', $bundle, $field_name);
    if (!$field) {
      $bad[] = $bundle . '.' . $field_name . ' tidak ada';
    }
    elseif (!$field->isRequired()) {
      $bad[] = $bundle . '.' . $field_name . ' tidak wajib';
    }
  }
}

/**
 * Jenis hanya menunjuk vocabulary milik grupnya, dan term tidak dibuat dari form.
 */
function kh_check_jenis_locked($bundle, &$bad) {
  $jenis = FieldConfig::loadByName('node', $bundle, 'field_jenis');
  if ($jenis) {
    $targets = kh_check_target_vocabularies($jenis);
    if ($targets !== ['jenis_' . $bundle]) {
      $bad[] = $bundle . '.field_jenis menunjuk ' . implode(',', $targets);
    }
    if (kh_check_allows_auto_create($jenis)) {
      $bad[] = $bundle . '.field_jenis auto_create aktif';
    }
  }

  foreach (['field_departemen', 'field_topik'] as $field_name) {
    $field = FieldConfig::loadByName('node', $bundle, $field_name);
    if ($field && kh_check_allows_auto_create($field)) {
      $bad[] = $bundle . '.' . $field_name . ' auto_create aktif';
    }
  }
}

/**
 * Field khusus: Difficulty hanya di Pembelajaran, Audience hanya di tiga tipe
 * dan tidak wajib.
 */
function kh_check_special_fields($bundle, &$bad) {
  $has_difficulty = (bool) FieldConfig::loadByName('node', $bundle, 'field_difficulty');
  if ($has_difficulty !== ($bundle === 'pembelajaran')) {
    $bad[] = 'field_difficulty salah di ' . $bundle;
  }

  $audience = FieldConfig::loadByName('node', $bundle, 'field_audience');
  $should_have_audience = in_array($bundle, ['prosedur', 'referensi', 'pembelajaran']);
  if ((bool) $audience !== $should_have_audience) {
    $bad[] = 'field_audience salah di ' . $bundle;
  }
  if ($audience && $audience->isRequired()) {
    $bad[] = $bundle . '.field_audience harus opsional';
  }
}

/**
 * Node Bantuan tanpa Ringkasan harus ditolak validasi.
 */
function kh_check_summary_required(&$bad) {
  $node = Node::create(['type' => 'bantuan', 'title' => 'Uji tanpa ringkasan']);

  $failed_fields = [];
  foreach ($node->validate() as $violation) {
    $failed_fields[] = $violation->getPropertyPath();
  }

  if (!in_array('field_ringkasan', $failed_fields)) {
    $bad[] = 'node tanpa ringkasan lolos validasi';
  }
}

foreach (KH_CHECK_BUNDLES as $bundle) {
  if (!NodeType::load($bundle)) {
    $bad[] = 'content type ' . $bundle . ' tidak ada';
    continue;
  }
  kh_check_required_fields($bundle, $bad);
  kh_check_jenis_locked($bundle, $bad);
}

foreach (KH_CHECK_BUNDLES as $bundle) {
  kh_check_special_fields($bundle, $bad);
}

if (NodeType::load('bantuan')) {
  kh_check_summary_required($bad);
}

kh_check_report($bad);
