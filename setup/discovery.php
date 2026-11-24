<?php

/**
 * Penemuan konten: alias URL (Pathauto) dan metadata mesin (Metatag + Schema.org).
 * Aman diulang.
 */

use Drupal\metatag\Entity\MetatagDefaults;
use Drupal\pathauto\Entity\PathautoPattern;

// Content type => [awalan alamat, tipe schema.org].
$bundles = [
  'prosedur' => ['/prosedur', 'WebPage'],
  'referensi' => ['/referensi', 'Article'],
  'pembelajaran' => ['/pembelajaran', 'Course'],
  'bantuan' => ['/bantuan', 'WebPage'],
  'alat_kerja' => ['/alat-kerja', 'WebPage'],
];

/**
 * Buat atau perbarui pola alamat Pathauto.
 *
 * $selection_condition dipakai untuk membatasi pola ke satu content type.
 */
function kh_save_pathauto_pattern($id, $label, $entity_type, $pattern_text, $selection_condition = NULL) {
  $pattern = PathautoPattern::load($id);
  if (!$pattern) {
    $pattern = PathautoPattern::create([
      'id' => $id,
      'label' => $label,
      'type' => $entity_type,
      'weight' => 0,
    ]);
    if ($selection_condition) {
      $pattern->addSelectionCondition($selection_condition);
    }
  }
  $pattern->setPattern($pattern_text);
  $pattern->save();
}

/**
 * Pola alamat: satu per content type, satu untuk semua term.
 */
function kh_setup_aliases($bundles) {
  foreach ($bundles as $bundle => $settings) {
    $prefix = $settings[0];

    $condition = [
      'id' => 'entity_bundle:node',
      'bundles' => [$bundle => $bundle],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ];

    kh_save_pathauto_pattern(
      'kh_' . $bundle,
      'Knowledge Hub: ' . $bundle,
      'canonical_entities:node',
      $prefix . '/[node:title]',
      $condition
    );
  }

  kh_save_pathauto_pattern(
    'kh_term',
    'Knowledge Hub: term',
    'canonical_entities:taxonomy_term',
    '/[term:vocabulary]/[term:name]'
  );
}

/**
 * Tag metadata sesuai tipe schema.org.
 *
 * Semua tipe memakai Ringkasan sebagai meta description.
 */
function kh_metatag_tags($schema_type) {
  $tags = ['description' => '[node:field_ringkasan]'];

  if ($schema_type === 'Article') {
    $tags['schema_article_type'] = 'Article';
    $tags['schema_article_headline'] = '[node:title]';
    $tags['schema_article_description'] = '[node:field_ringkasan]';
    $tags['schema_article_date_published'] = '[node:created:custom:c]';
    $tags['schema_article_date_modified'] = '[node:changed:custom:c]';
  }
  elseif ($schema_type === 'Course') {
    $tags['schema_course_type'] = 'Course';
    $tags['schema_course_name'] = '[node:title]';
    $tags['schema_course_description'] = '[node:field_ringkasan]';
  }
  else {
    $tags['schema_web_page_type'] = 'WebPage';
    $tags['schema_web_page_description'] = '[node:field_ringkasan]';
  }

  return $tags;
}

/**
 * Metatag: meta description dari Ringkasan dan JSON-LD per content type.
 */
function kh_setup_metatags($bundles) {
  foreach ($bundles as $bundle => $settings) {
    $schema_type = $settings[1];

    $defaults = MetatagDefaults::load('node__' . $bundle);
    if (!$defaults) {
      $defaults = MetatagDefaults::create([
        'id' => 'node__' . $bundle,
        'label' => 'Content: ' . $bundle,
      ]);
    }
    $defaults->set('tags', kh_metatag_tags($schema_type));
    $defaults->save();
  }
}

/**
 * Bangkitkan alias untuk node dan term yang sudah ada.
 */
function kh_generate_aliases() {
  $generator = \Drupal::service('pathauto.generator');
  $entity_manager = \Drupal::entityTypeManager();

  foreach (['node', 'taxonomy_term'] as $entity_type) {
    foreach ($entity_manager->getStorage($entity_type)->loadMultiple() as $entity) {
      $generator->updateEntityAlias($entity, 'bulk');
    }
  }
}

kh_setup_aliases($bundles);
kh_setup_metatags($bundles);

// Judul diganti: alias lama tetap hidup (UPDATE_ACTION_LEAVE = 1), supaya bookmark tidak 404.
$pathauto_settings = \Drupal::configFactory()->getEditable('pathauto.settings');
$pathauto_settings->set('update_action', 1);
$pathauto_settings->save();

kh_generate_aliases();

drupal_flush_all_caches();
echo "discovery (alias, metadata) ok\n";
