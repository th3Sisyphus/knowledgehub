<?php

/**
 * Pasang tema kh_theme, tempatkan blok, dan atur beranda.
 *
 * Aman diulang: blok kh_theme_* dihapus lalu dibuat ulang.
 * Prasyarat: search.php (facet dan view terkait) sudah dijalankan.
 */

use Drupal\block\Entity\Block;
use Drupal\facets\Entity\Facet;

const KH_THEME = 'kh_theme';

const KH_BUNDLES = ['prosedur', 'referensi', 'pembelajaran', 'bantuan', 'alat_kerja'];

// Content type => alamat halaman daftarnya. Dipakai untuk membatasi facet.
const KH_GROUP_PATHS = [
  'prosedur' => '/prosedur',
  'referensi' => '/referensi',
  'pembelajaran' => '/pembelajaran',
  'bantuan' => '/bantuan',
  'alat_kerja' => '/alat-kerja',
];

/**
 * Pasang tema, jadikan tema default, dan atur beranda.
 */
function kh_install_theme() {
  \Drupal::service('theme_installer')->install([KH_THEME, 'claro']);

  $theme_config = \Drupal::configFactory()->getEditable('system.theme');
  $theme_config->set('default', KH_THEME);
  $theme_config->set('admin', 'claro');
  $theme_config->save();

  $site_config = \Drupal::configFactory()->getEditable('system.site');
  $site_config->set('page.front', '/beranda');
  $site_config->set('slogan', 'Pusat Pengetahuan Internal');
  $site_config->save();
}

/**
 * Hapus semua blok milik tema ini supaya penempatan bisa dibuat ulang.
 */
function kh_remove_theme_blocks() {
  foreach (Block::loadMultiple() as $block) {
    if ($block->getTheme() === KH_THEME) {
      $block->delete();
    }
  }
}

/**
 * Tempatkan satu blok di sebuah region.
 *
 * Opsi: label, label_display, provider, weight, visibility.
 */
function kh_place_block($id, $plugin, $region, $options = []) {
  $label = $id;
  if (isset($options['label'])) {
    $label = $options['label'];
  }

  $label_display = '0';
  if (isset($options['label_display'])) {
    $label_display = $options['label_display'];
  }

  // Provider bawaan: bagian pertama nama plugin, contoh "system_menu_block".
  $plugin_parts = explode(':', $plugin);
  $provider = $plugin_parts[0];
  if (isset($options['provider'])) {
    $provider = $options['provider'];
  }

  $weight = 0;
  if (isset($options['weight'])) {
    $weight = $options['weight'];
  }

  $visibility = [];
  if (isset($options['visibility'])) {
    $visibility = $options['visibility'];
  }

  Block::create([
    'id' => KH_THEME . '_' . $id,
    'theme' => KH_THEME,
    'region' => $region,
    'plugin' => $plugin,
    'status' => TRUE,
    'weight' => $weight,
    'settings' => [
      'id' => $plugin,
      'label' => $label,
      'label_display' => $label_display,
      'provider' => $provider,
    ],
    'visibility' => $visibility,
  ])->save();
}

/**
 * Blok dasar: menu utama, pesan, breadcrumb, judul, konten, bantuan.
 */
function kh_place_basic_blocks() {
  kh_place_block('main_menu', 'system_menu_block:main', 'primary_menu', [
    'label' => 'Menu utama',
    'provider' => 'system',
  ]);
  kh_place_block('messages', 'system_messages_block', 'highlighted', [
    'label' => 'Pesan',
    'provider' => 'system',
  ]);
  kh_place_block('breadcrumb', 'system_breadcrumb_block', 'breadcrumb', [
    'label' => 'Breadcrumb',
    'provider' => 'system',
  ]);
  kh_place_block('title', 'page_title_block', 'page_title', [
    'label' => 'Judul halaman',
    'provider' => 'core',
  ]);
  kh_place_block('content', 'system_main_block', 'content', [
    'label' => 'Konten utama',
    'provider' => 'system',
  ]);
  kh_place_block('help', 'help_block', 'help', [
    'label' => 'Bantuan',
    'provider' => 'help',
  ]);
}

/**
 * Cari content type dari id facet, contoh "kh_prosedur_topik" => "prosedur".
 * Mengembalikan NULL bila id tidak cocok.
 */
function kh_bundle_of_facet($facet_id) {
  $pattern = '/^kh_([a-z_]+?)_(departemen|audience|topik|difficulty|jenis)$/';
  if (!preg_match($pattern, $facet_id, $matches)) {
    return NULL;
  }

  $bundle = $matches[1];
  if (!isset(KH_GROUP_PATHS[$bundle])) {
    return NULL;
  }
  return $bundle;
}

/**
 * Facet per grup tampil di kolom kiri, hanya pada halaman grup itu.
 */
function kh_place_facet_blocks() {
  foreach (Facet::loadMultiple() as $facet) {
    $bundle = kh_bundle_of_facet($facet->id());
    if ($bundle === NULL) {
      continue;
    }

    kh_place_block('facet_' . $facet->id(), 'facet_block:' . $facet->id(), 'sidebar_first', [
      'label' => $facet->label(),
      'label_display' => 'visible',
      'provider' => 'facets',
      'weight' => (int) $facet->getWeight(),
      'visibility' => [
        'request_path' => [
          'id' => 'request_path',
          'negate' => FALSE,
          'pages' => KH_GROUP_PATHS[$bundle],
        ],
      ],
    ]);
  }
}

/**
 * Konten terkait di kolom kanan halaman konten.
 */
function kh_place_related_block() {
  kh_place_block('related', 'views_block:kh_terkait-block_1', 'sidebar_second', [
    'label' => 'Konten terkait',
    'label_display' => 'visible',
    'provider' => 'views',
    'visibility' => [
      'entity_bundle:node' => [
        'id' => 'entity_bundle:node',
        'negate' => FALSE,
        'context_mapping' => ['node' => '@node.node_route_context:node'],
        'bundles' => array_combine(KH_BUNDLES, KH_BUNDLES),
      ],
    ],
  ]);
}

kh_install_theme();
kh_remove_theme_blocks();
kh_place_basic_blocks();
kh_place_facet_blocks();
kh_place_related_block();

drupal_flush_all_caches();
echo "theme ok\n";
