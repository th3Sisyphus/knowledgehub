<?php

/**
 * Cek alias URL, meta description, dan JSON-LD per content type.
 * Mencetak OK bila lulus, selain itu daftar masalah.
 */

require_once __DIR__ . '/helpers.php';

const KH_SEO_BUNDLES = ['prosedur', 'referensi', 'pembelajaran', 'bantuan', 'alat_kerja'];

// Content type => tipe schema.org yang diharapkan di JSON-LD.
const KH_SEO_TYPES = [
  'prosedur' => 'WebPage',
  'referensi' => 'Article',
  'pembelajaran' => 'Course',
  'bantuan' => 'WebPage',
  'alat_kerja' => 'WebPage',
];

$bad = [];

/**
 * Satu node terbit dari content type ini, atau NULL.
 */
function kh_seo_published_node($bundle) {
  $query = \Drupal::entityQuery('node');
  $query->accessCheck(FALSE);
  $query->condition('type', $bundle);
  $query->condition('status', 1);
  $query->range(0, 1);
  $ids = $query->execute();

  if (empty($ids)) {
    return NULL;
  }
  return \Drupal::entityTypeManager()->getStorage('node')->load(reset($ids));
}

/**
 * Tipe schema.org dari satu blok JSON-LD, atau NULL bila tidak ada.
 */
function kh_seo_json_ld_type($json) {
  if (!$json || !isset($json['@context'])) {
    return NULL;
  }
  if (isset($json['@type'])) {
    return $json['@type'];
  }
  if (isset($json['@graph'][0]['@type'])) {
    return $json['@graph'][0]['@type'];
  }
  return NULL;
}

/**
 * Cek satu halaman konten: status 200, meta description, dan JSON-LD.
 * Mengembalikan tipe schema.org yang ditemukan, atau NULL.
 */
function kh_seo_check_page($bundle, &$bad) {
  $node = kh_seo_published_node($bundle);
  $path = kh_check_alias($node);

  $prefix = '/' . str_replace('_', '-', $bundle) . '/';
  if (strpos($path, $prefix) !== 0) {
    $bad[] = $bundle . ": alias '" . $path . "' tanpa awalan grup";
  }

  $response = kh_check_request($path);
  if ($response->getStatusCode() !== 200) {
    $bad[] = $bundle . ': ' . $path . ' status ' . $response->getStatusCode();
    return NULL;
  }
  $html = (string) $response->getBody();

  // Meta description harus sama dengan Ringkasan.
  $summary = $node->get('field_ringkasan')->value;
  $has_meta = preg_match('#<meta name="description" content="([^"]*)"#', $html, $meta);
  if (!$has_meta || html_entity_decode($meta[1]) !== $summary) {
    $bad[] = $bundle . ': meta description tidak sama dengan Ringkasan';
  }

  // JSON-LD harus ada dan valid.
  $has_json_ld = preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);
  if (!$has_json_ld) {
    $bad[] = $bundle . ': tidak ada JSON-LD';
    return NULL;
  }

  $type = kh_seo_json_ld_type(json_decode($blocks[1][0], TRUE));
  if ($type === NULL) {
    $bad[] = $bundle . ': JSON-LD tidak valid';
  }
  return $type;
}

/**
 * Alias SOP Perjalanan Dinas harus tetap dan terbaca.
 */
function kh_seo_check_sop_alias(&$bad) {
  $alias = kh_check_alias(kh_check_node('sop-perjalanan-dinas'));
  if ($alias !== '/prosedur/sop-perjalanan-dinas') {
    $bad[] = "alias SOP salah: '" . $alias . "'";
  }
}

/**
 * Alias term, contoh Topik "Cuti" menjadi /topik/cuti.
 */
function kh_seo_check_term_alias(&$bad) {
  $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $found = $terms->loadByProperties(['vid' => 'topik', 'name' => 'Cuti']);

  $alias = '';
  if (!empty($found)) {
    $term = reset($found);
    $manager = \Drupal::service('path_alias.manager');
    $alias = $manager->getAliasByPath('/taxonomy/term/' . $term->id());
  }

  if ($alias !== '/topik/cuti') {
    $bad[] = "alias term Cuti: '" . $alias . "'";
  }
}

/**
 * Dua node berjudul sama harus mendapat alias berbeda dan keduanya terbuka.
 */
function kh_seo_check_duplicate_titles(&$bad) {
  $sop = kh_check_node('sop-perjalanan-dinas');

  $copies = [];
  foreach ([0, 1] as $number) {
    $copy = $sop->createDuplicate();
    $copy->set('title', 'Judul Kembar Uji');
    $copy->set('field_slug_asal', 'uji-kembar-' . $number);
    $copy->set('moderation_state', 'published');
    $copy->save();
    $copies[] = $copy;
  }

  $first = kh_check_alias($copies[0]);
  $second = kh_check_alias($copies[1]);

  if ($first === $second) {
    $bad[] = 'alias kembar bentrok: ' . $first;
  }
  foreach ([$first, $second] as $path) {
    if (kh_check_request($path)->getStatusCode() !== 200) {
      $bad[] = 'alias kembar ' . $path . ' tidak 200';
    }
  }

  foreach ($copies as $copy) {
    $copy->delete();
  }
}

kh_seo_check_sop_alias($bad);

foreach (KH_SEO_BUNDLES as $bundle) {
  $type = kh_seo_check_page($bundle, $bad);
  $expected = KH_SEO_TYPES[$bundle];
  if ($type !== NULL && $type !== $expected) {
    $bad[] = $bundle . ': @type ' . $type . ', harus ' . $expected;
  }
}

kh_seo_check_term_alias($bad);
kh_seo_check_duplicate_titles($bad);

kh_check_report($bad);
