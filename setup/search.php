<?php

/**
 * Pencarian, katalog per grup dengan facet, menu utama, dan blok Konten terkait.
 *
 * Aman diulang: objek dengan id kh_* dihapus lalu dibuat ulang.
 * Prasyarat: model.php, seed_terms.php, import_content.php.
 */

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\facets\Entity\Facet;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\views\Entity\View;

const KH_INDEX_TABLE = 'search_api_index_kh_index';

$bundles = ['prosedur', 'referensi', 'pembelajaran', 'bantuan', 'alat_kerja'];

// Grup: halaman, nama menu, dan facet (field => label). Urutan = urutan di sidebar.
$groups = [
  'prosedur' => [
    'path' => 'prosedur',
    'label' => 'Prosedur',
    'facets' => [
      'field_departemen' => 'Departemen',
      'field_audience' => 'Audience',
      'field_topik' => 'Topik',
      'field_jenis' => 'Jenis',
    ],
  ],
  'referensi' => [
    'path' => 'referensi',
    'label' => 'Referensi',
    'facets' => [
      'field_departemen' => 'Departemen',
      'field_audience' => 'Audience',
      'field_topik' => 'Topik',
      'field_jenis' => 'Jenis',
    ],
  ],
  'pembelajaran' => [
    'path' => 'pembelajaran',
    'label' => 'Pembelajaran',
    'facets' => [
      'field_audience' => 'Audience',
      'field_difficulty' => 'Difficulty',
      'field_topik' => 'Topik',
      'field_jenis' => 'Jenis',
    ],
  ],
  'bantuan' => [
    'path' => 'bantuan',
    'label' => 'Bantuan',
    'facets' => [
      'field_departemen' => 'Departemen',
      'field_topik' => 'Topik',
      'field_jenis' => 'Jenis',
    ],
  ],
  'alat_kerja' => [
    'path' => 'alat-kerja',
    'label' => 'Alat Kerja',
    'facets' => [
      'field_departemen' => 'Departemen',
    ],
  ],
];

/**
 * Hapus entity bila ada.
 */
function kh_delete_if_exists($entity) {
  if ($entity) {
    $entity->delete();
  }
}

/**
 * Tampilan teaser per content type: Jenis, Departemen, Ringkasan.
 * Judul sudah ditampilkan oleh template tema.
 */
function kh_setup_teasers($bundles) {
  foreach ($bundles as $bundle) {
    $display = EntityViewDisplay::load('node.' . $bundle . '.teaser');
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $bundle,
        'mode' => 'teaser',
        'status' => TRUE,
      ]);
    }

    foreach ($display->getComponents() as $name => $component) {
      $display->removeComponent($name);
    }

    $display->setComponent('field_jenis', [
      'type' => 'entity_reference_label',
      'label' => 'hidden',
      'weight' => 1,
      'settings' => ['link' => FALSE],
    ]);
    $display->setComponent('field_departemen', [
      'type' => 'entity_reference_label',
      'label' => 'hidden',
      'weight' => 2,
      'settings' => ['link' => FALSE],
    ]);
    $display->setComponent('field_ringkasan', [
      'type' => 'string',
      'label' => 'hidden',
      'weight' => 3,
      'settings' => ['link_to_entity' => FALSE],
    ]);
    $display->save();
  }
}

/**
 * Satu field index Search API.
 */
function kh_index_field($label, $path, $type, $boost = NULL) {
  $field = [
    'label' => $label,
    'datasource_id' => 'entity:node',
    'property_path' => $path,
    'type' => $type,
  ];
  if ($boost !== NULL) {
    $field['boost'] = $boost;
  }
  return $field;
}

/**
 * Server dan index Search API (backend database).
 */
function kh_setup_search_index($bundles) {
  kh_delete_if_exists(Index::load('kh_index'));
  kh_delete_if_exists(Server::load('kh_db'));

  Server::create([
    'id' => 'kh_db',
    'name' => 'Database Knowledge Hub',
    'backend' => 'search_api_db',
    'status' => TRUE,
    'backend_config' => [
      'database' => 'default:default',
      'min_chars' => 2,
      'matching' => 'words',
      'phrase' => 'none',
      'autocomplete' => [
        'min_chars' => 1,
        'suggest_suffix' => TRUE,
        'suggest_words' => TRUE,
      ],
    ],
  ])->save();

  $fields = [
    'title' => kh_index_field('Judul', 'title', 'text', 5.0),
    'title_sort' => kh_index_field('Judul (urut)', 'title', 'string'),
    'body' => kh_index_field('Isi', 'body', 'text', 1.0),
    'field_ringkasan' => kh_index_field('Ringkasan', 'field_ringkasan', 'text', 3.0),
    'type' => kh_index_field('Content type', 'type', 'string'),
    'field_departemen' => kh_index_field('Departemen', 'field_departemen', 'integer'),
    'field_audience' => kh_index_field('Audience', 'field_audience', 'integer'),
    'field_topik' => kh_index_field('Topik', 'field_topik', 'integer'),
    'field_difficulty' => kh_index_field('Difficulty', 'field_difficulty', 'integer'),
    'field_jenis' => kh_index_field('Jenis', 'field_jenis', 'integer'),
  ];

  $processors = [
    'entity_status' => [
      'weights' => ['preprocess_index' => -30],
    ],
    'content_access' => [
      'weights' => ['preprocess_index' => -6, 'preprocess_query' => -4],
    ],
    'html_filter' => [
      'weights' => ['preprocess_index' => -3, 'preprocess_query' => -3],
      'all_fields' => FALSE,
      'fields' => ['body'],
      'title' => TRUE,
      'alt' => TRUE,
      'tags' => ['h1' => 5, 'h2' => 3, 'h3' => 2, 'strong' => 2, 'b' => 2],
    ],
    'ignorecase' => [
      'weights' => ['preprocess_index' => -20, 'preprocess_query' => -20],
      'all_fields' => TRUE,
      'fields' => [],
    ],
    'tokenizer' => [
      'weights' => ['preprocess_index' => -6, 'preprocess_query' => -6],
      'all_fields' => TRUE,
      'fields' => [],
      'spaces' => '',
      'ignored' => '._-',
      'overlap_cjk' => 1,
      'minimum_word_size' => 2,
    ],
  ];

  Index::create([
    'id' => 'kh_index',
    'name' => 'Indeks Knowledge Hub',
    'server' => 'kh_db',
    'status' => TRUE,
    'datasource_settings' => [
      'entity:node' => [
        'bundles' => ['default' => FALSE, 'selected' => $bundles],
        'languages' => ['default' => TRUE, 'selected' => []],
      ],
    ],
    'tracker_settings' => ['default' => ['indexing_order' => 'fifo']],
    'field_settings' => $fields,
    'processor_settings' => $processors,
    'options' => [
      'index_directly' => TRUE,
      'cron_limit' => 50,
      'track_changes_in_references' => TRUE,
    ],
  ])->save();

  drupal_flush_all_caches();
  $index = Index::load('kh_index');
  $index->rebuildTracker();
  $index->indexItems();
}

/**
 * Pengaturan dasar satu item Views (filter, sort, field).
 */
function kh_views_item($id, $table, $field, $extra = []) {
  $base = [
    'id' => $id,
    'table' => $table,
    'field' => $field,
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
  ];
  return $extra + $base;
}

/**
 * Filter "Content type" untuk halaman satu grup.
 */
function kh_view_type_filter($bundle) {
  $views_data = \Drupal::service('views.views_data')->get(KH_INDEX_TABLE);
  $plugin = 'search_api_string';
  if (isset($views_data['type']['filter']['id'])) {
    $plugin = $views_data['type']['filter']['id'];
  }

  $operator = '=';
  $value = $bundle;
  if ($plugin === 'search_api_options') {
    $operator = 'or';
    $value = [$bundle => $bundle];
  }

  return kh_views_item('type', KH_INDEX_TABLE, 'type', [
    'plugin_id' => $plugin,
    'operator' => $operator,
    'value' => $value,
    'group' => 1,
    'exposed' => FALSE,
    'expose' => [
      'operator_id' => '',
      'label' => '',
      'description' => '',
      'use_operator' => FALSE,
      'operator' => '',
      'identifier' => '',
      'required' => FALSE,
      'remember' => FALSE,
      'multiple' => FALSE,
      'remember_roles' => ['authenticated' => 'authenticated'],
    ],
    'is_grouped' => FALSE,
    'group_info' => kh_empty_group_info(),
  ]);
}

/**
 * Pengaturan grup filter yang tidak dipakai.
 */
function kh_empty_group_info() {
  return [
    'label' => '',
    'description' => '',
    'identifier' => '',
    'optional' => TRUE,
    'widget' => 'select',
    'multiple' => FALSE,
    'remember' => FALSE,
    'default_group' => 'All',
    'default_group_multiple' => [],
    'group_items' => [],
  ];
}

/**
 * Urutan: pencarian memakai relevansi, daftar grup memakai judul.
 */
function kh_view_sort($field, $order) {
  return [
    $field => kh_views_item($field, KH_INDEX_TABLE, $field, [
      'plugin_id' => 'search_api',
      'order' => $order,
      'exposed' => FALSE,
      'expose' => ['label' => ''],
    ]),
  ];
}

/**
 * Teks yang tampil saat tidak ada hasil.
 */
function kh_view_empty_text() {
  return [
    'area_text_custom' => kh_views_item('area_text_custom', 'views', 'area_text_custom', [
      'plugin_id' => 'text_custom',
      'empty' => TRUE,
      'tokenize' => FALSE,
      'content' => '<p>Tidak ada hasil. Ubah kata kunci atau hapus filter.</p>',
    ]),
  ];
}

/**
 * Pengaturan pager dan form dasar untuk tampilan default katalog.
 */
function kh_view_default_display($bundles) {
  $view_modes = [];
  foreach ($bundles as $bundle) {
    $view_modes[$bundle] = 'teaser';
  }

  return [
    'display_plugin' => 'default',
    'id' => 'default',
    'display_title' => 'Default',
    'position' => 0,
    'display_options' => [
      'title' => 'Katalog',
      'fields' => [],
      'query' => [
        'type' => 'search_api_query',
        'options' => [
          'bypass_access' => FALSE,
          'skip_access' => FALSE,
          'preserve_facet_query_args' => FALSE,
        ],
      ],
      'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
      'cache' => ['type' => 'none', 'options' => []],
      'exposed_form' => [
        'type' => 'basic',
        'options' => [
          'submit_button' => 'Cari',
          'reset_button' => FALSE,
          'reset_button_label' => 'Reset',
          'exposed_sorts_label' => 'Urutkan',
          'expose_sort_order' => FALSE,
          'sort_asc_label' => 'Naik',
          'sort_desc_label' => 'Turun',
        ],
      ],
      'pager' => [
        'type' => 'full',
        'options' => [
          'items_per_page' => 12,
          'offset' => 0,
          'id' => 0,
          'total_pages' => NULL,
          'quantity' => 9,
          'tags' => [
            'previous' => '‹ Sebelumnya',
            'next' => 'Berikutnya ›',
            'first' => '« Pertama',
            'last' => 'Terakhir »',
          ],
          'expose' => [
            'items_per_page' => FALSE,
            'items_per_page_label' => 'Item per halaman',
            'items_per_page_options' => '5, 10, 25',
            'items_per_page_options_all' => FALSE,
            'items_per_page_options_all_label' => '- Semua -',
            'offset' => FALSE,
            'offset_label' => 'Offset',
          ],
        ],
      ],
      'style' => [
        'type' => 'default',
        'options' => ['grouping' => [], 'row_class' => '', 'default_row_class' => TRUE],
      ],
      'row' => [
        'type' => 'search_api',
        'options' => ['view_modes' => ['entity:node' => $view_modes]],
      ],
      'filters' => [],
      'sorts' => kh_view_sort('title_sort', 'ASC'),
      'arguments' => [],
      'relationships' => [],
      'empty' => kh_view_empty_text(),
      'header' => [],
      'footer' => [],
      'display_extenders' => [],
      'use_ajax' => FALSE,
    ],
  ];
}

/**
 * Halaman daftar untuk satu grup (contoh /prosedur).
 */
function kh_view_group_page($bundle, $group, $position) {
  return [
    'display_plugin' => 'page',
    'id' => 'page_' . $bundle,
    'display_title' => $group['label'],
    'position' => $position,
    'display_options' => [
      'display_description' => '',
      'path' => $group['path'],
      'title' => $group['label'],
      'defaults' => ['title' => FALSE, 'filters' => FALSE, 'filter_groups' => FALSE],
      'filters' => ['type' => kh_view_type_filter($bundle)],
      'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
      'display_extenders' => [],
    ],
  ];
}

/**
 * Halaman pencarian /cari: kotak teks, urut relevansi.
 */
function kh_view_search_page($position) {
  $fulltext = kh_views_item('search_api_fulltext', KH_INDEX_TABLE, 'search_api_fulltext', [
    'plugin_id' => 'search_api_fulltext',
    'operator' => 'or',
    'value' => '',
    'group' => 1,
    'exposed' => TRUE,
    'expose' => [
      'operator_id' => 'search_api_fulltext_op',
      'label' => 'Cari di Knowledge Hub',
      'description' => '',
      'use_operator' => FALSE,
      'operator' => 'search_api_fulltext_op',
      'identifier' => 'search_api_fulltext',
      'required' => FALSE,
      'remember' => FALSE,
      'multiple' => FALSE,
      'remember_roles' => ['authenticated' => 'authenticated'],
    ],
    'is_grouped' => FALSE,
    'group_info' => kh_empty_group_info(),
    'parse_mode' => 'terms',
    'min_length' => NULL,
    'fields' => ['title', 'body', 'field_ringkasan'],
  ]);

  return [
    'display_plugin' => 'page',
    'id' => 'page_cari',
    'display_title' => 'Cari',
    'position' => $position,
    'display_options' => [
      'display_description' => '',
      'path' => 'cari',
      'title' => 'Cari',
      'defaults' => ['title' => FALSE, 'filters' => FALSE, 'filter_groups' => FALSE, 'sorts' => FALSE],
      'sorts' => kh_view_sort('search_api_relevance', 'DESC'),
      'filters' => ['search_api_fulltext' => $fulltext],
      'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
      'display_extenders' => [],
    ],
  ];
}

/**
 * View katalog: daftar per grup dan halaman Cari.
 */
function kh_setup_catalog_view($bundles, $groups) {
  $displays = ['default' => kh_view_default_display($bundles)];

  foreach ($groups as $bundle => $group) {
    $displays['page_' . $bundle] = kh_view_group_page($bundle, $group, count($displays));
  }
  $displays['page_cari'] = kh_view_search_page(count($displays));

  kh_delete_if_exists(View::load('kh_katalog'));
  View::create([
    'id' => 'kh_katalog',
    'label' => 'Katalog Knowledge Hub',
    'module' => 'views',
    'status' => TRUE,
    'base_table' => KH_INDEX_TABLE,
    'base_field' => 'search_api_id',
    'description' => 'Daftar per grup dan pencarian.',
    'display' => $displays,
  ])->save();
}

/**
 * Kolom judul (link) untuk view Konten terkait.
 */
function kh_related_title_field() {
  return kh_views_item('title', 'node_field_data', 'title', [
    'entity_type' => 'node',
    'entity_field' => 'title',
    'plugin_id' => 'field',
    'label' => '',
    'exclude' => FALSE,
    'alter' => ['alter_text' => FALSE],
    'element_type' => '',
    'element_class' => '',
    'element_label_type' => '',
    'element_label_class' => '',
    'element_label_colon' => FALSE,
    'element_wrapper_type' => '',
    'element_wrapper_class' => '',
    'element_default_classes' => TRUE,
    'empty' => '',
    'hide_empty' => FALSE,
    'empty_zero' => FALSE,
    'hide_alter_empty' => TRUE,
    'click_sort_column' => 'value',
    'type' => 'string',
    'settings' => ['link_to_entity' => TRUE],
    'group_column' => 'value',
    'group_columns' => [],
    'group_rows' => TRUE,
    'delta_limit' => 0,
    'delta_offset' => 0,
    'delta_reversed' => FALSE,
    'delta_first_last' => FALSE,
    'multi_type' => 'separator',
    'separator' => ', ',
    'field_api_classes' => FALSE,
  ]);
}

/**
 * Argumen view Konten terkait: Topik sama dengan node yang dibuka,
 * dan bukan node itu sendiri.
 */
function kh_related_arguments() {
  $exception = ['value' => 'all', 'title_enable' => FALSE, 'title' => 'Semua'];
  $summary = ['sort_order' => 'asc', 'number_of_records' => 0, 'format' => 'default_summary'];
  $summary_options = ['items_per_page' => 25, 'offset' => 0];
  $validation = ['type' => 'none', 'fail' => 'not found'];

  $not_this_node = kh_views_item('nid', 'node_field_data', 'nid', [
    'entity_type' => 'node',
    'entity_field' => 'nid',
    'plugin_id' => 'node_nid',
    'default_action' => 'default',
    'exception' => $exception,
    'default_argument_type' => 'node',
    'default_argument_options' => [],
    'summary' => $summary,
    'summary_options' => $summary_options,
    'specify_validation' => FALSE,
    'validate' => $validation,
    'validate_options' => [],
    'break_phrase' => FALSE,
    'not' => TRUE,
  ]);

  $same_topic = kh_views_item('field_topik_target_id', 'node__field_topik', 'field_topik_target_id', [
    'entity_type' => 'node',
    'entity_field' => 'field_topik',
    'plugin_id' => 'numeric',
    'default_action' => 'default',
    'exception' => $exception,
    'default_argument_type' => 'taxonomy_tid',
    'default_argument_options' => [
      'term_page' => '',
      'node' => TRUE,
      'anyall' => '+',
      'limit' => TRUE,
      'vids' => ['topik' => 'topik'],
    ],
    'summary' => $summary,
    'summary_options' => $summary_options,
    'specify_validation' => FALSE,
    'validate' => $validation,
    'validate_options' => [],
    'break_phrase' => TRUE,
    'not' => FALSE,
  ]);

  return [
    'nid' => $not_this_node,
    'field_topik_target_id' => $same_topic,
  ];
}

/**
 * View blok Konten terkait: node lain dengan Topik sama, maksimal 5.
 */
function kh_setup_related_view() {
  $status_filter = kh_views_item('status', 'node_field_data', 'status', [
    'entity_type' => 'node',
    'entity_field' => 'status',
    'plugin_id' => 'boolean',
    'value' => '1',
    'group' => 1,
    'expose' => ['operator' => ''],
  ]);

  $changed_sort = kh_views_item('changed', 'node_field_data', 'changed', [
    'entity_type' => 'node',
    'entity_field' => 'changed',
    'plugin_id' => 'date',
    'order' => 'DESC',
    'exposed' => FALSE,
    'expose' => ['label' => ''],
    'granularity' => 'second',
  ]);

  // Urutan kedua: bila tanggal ubah sama, judul menentukan urutan supaya tidak berganti-ganti.
  $title_sort = kh_views_item('title_1', 'node_field_data', 'title', [
    'entity_type' => 'node',
    'entity_field' => 'title',
    'plugin_id' => 'standard',
    'order' => 'ASC',
    'exposed' => FALSE,
    'expose' => ['label' => ''],
  ]);

  $default_display = [
    'display_plugin' => 'default',
    'id' => 'default',
    'display_title' => 'Default',
    'position' => 0,
    'display_options' => [
      'title' => 'Konten terkait',
      'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
      'cache' => ['type' => 'none', 'options' => []],
      'query' => ['type' => 'views_query', 'options' => []],
      'exposed_form' => ['type' => 'basic', 'options' => []],
      'pager' => ['type' => 'some', 'options' => ['items_per_page' => 5, 'offset' => 0]],
      'style' => [
        'type' => 'html_list',
        'options' => [
          'grouping' => [],
          'row_class' => '',
          'default_row_class' => TRUE,
          'type' => 'ul',
          'wrapper_class' => 'item-list',
          'class' => '',
        ],
      ],
      'row' => [
        'type' => 'fields',
        'options' => [
          'default_field_elements' => TRUE,
          'inline' => [],
          'separator' => '',
          'hide_empty' => FALSE,
        ],
      ],
      'fields' => ['title' => kh_related_title_field()],
      'filters' => ['status' => $status_filter],
      'sorts' => ['changed' => $changed_sort, 'title' => $title_sort],
      'arguments' => kh_related_arguments(),
      'header' => [],
      'footer' => [],
      'empty' => [],
      'relationships' => [],
      'display_extenders' => [],
    ],
  ];

  $block_display = [
    'display_plugin' => 'block',
    'id' => 'block_1',
    'display_title' => 'Blok',
    'position' => 1,
    'display_options' => [
      'block_description' => 'Konten terkait',
      'block_hide_empty' => TRUE,
      'display_description' => '',
      'display_extenders' => [],
    ],
  ];

  kh_delete_if_exists(View::load('kh_terkait'));
  View::create([
    'id' => 'kh_terkait',
    'label' => 'Konten terkait',
    'module' => 'views',
    'status' => TRUE,
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => ['default' => $default_display, 'block_1' => $block_display],
  ])->save();
}

/**
 * Satu facet per field per grup, beserta bloknya.
 */
function kh_setup_facets($groups) {
  $theme = \Drupal::config('system.theme')->get('default');

  $processors = [
    'translate_entity' => [
      'processor_id' => 'translate_entity',
      'weights' => ['build' => 5],
      'settings' => [],
    ],
    'url_processor_handler' => [
      'processor_id' => 'url_processor_handler',
      'weights' => ['pre_query' => 50, 'build' => 15],
      'settings' => [],
    ],
    'display_value_widget_order' => [
      'processor_id' => 'display_value_widget_order',
      'weights' => ['sort' => 40],
      'settings' => ['sort' => 'ASC'],
    ],
  ];

  $widget = [
    'type' => 'checkbox',
    'config' => [
      'show_numbers' => TRUE,
      'soft_limit' => 0,
      'show_reset_link' => FALSE,
      'hide_reset_when_no_selection' => FALSE,
      'reset_text' => 'Tampilkan semua',
      'soft_limit_settings' => [
        'show_less_label' => 'Lebih sedikit',
        'show_more_label' => 'Lebih banyak',
      ],
    ],
  ];

  foreach ($groups as $bundle => $group) {
    $source = 'search_api:views_page__kh_katalog__page_' . $bundle;
    $weight = 0;

    foreach ($group['facets'] as $field => $label) {
      $short_name = substr($field, strlen('field_'));
      $id = 'kh_' . $bundle . '_' . $short_name;

      kh_delete_if_exists(Facet::load($id));
      kh_delete_if_exists(Block::load('facet_' . $id));

      Facet::create([
        'id' => $id,
        'name' => $label,
        'url_alias' => $short_name,
        'weight' => $weight,
        'min_count' => 1,
        'show_only_one_result' => FALSE,
        'field_identifier' => $field,
        'facet_source_id' => $source,
        'status' => TRUE,
        'widget' => $widget,
        'query_operator' => 'or',
        'use_hierarchy' => FALSE,
        'expand_hierarchy' => FALSE,
        'enable_parent_when_child_gets_disabled' => TRUE,
        'hard_limit' => 0,
        'exclude' => FALSE,
        'only_visible_when_facet_source_is_visible' => TRUE,
        'processor_configs' => $processors,
        'empty_behavior' => ['behavior' => 'none'],
      ])->save();

      Block::create([
        'id' => 'facet_' . $id,
        'theme' => $theme,
        'region' => 'sidebar',
        'weight' => $weight,
        'status' => TRUE,
        'plugin' => 'facet_block:' . $id,
        'settings' => [
          'id' => 'facet_block:' . $id,
          'label' => $label,
          'label_display' => 'visible',
          'provider' => 'facets',
        ],
      ])->save();

      $weight++;
    }
  }
}

/**
 * Blok Konten terkait di halaman node.
 */
function kh_setup_related_block($bundles) {
  $theme = \Drupal::config('system.theme')->get('default');

  kh_delete_if_exists(Block::load('kh_konten_terkait'));
  Block::create([
    'id' => 'kh_konten_terkait',
    'theme' => $theme,
    'region' => 'content',
    'weight' => 50,
    'status' => TRUE,
    'plugin' => 'views_block:kh_terkait-block_1',
    'settings' => [
      'id' => 'views_block:kh_terkait-block_1',
      'label' => 'Konten terkait',
      'label_display' => 'visible',
      'provider' => 'views',
      'views_label' => 'Konten terkait',
      'items_per_page' => 'none',
    ],
    'visibility' => [
      'entity_bundle:node' => [
        'id' => 'entity_bundle:node',
        'negate' => FALSE,
        'context_mapping' => ['node' => '@node.node_route_context:node'],
        'bundles' => array_combine($bundles, $bundles),
      ],
    ],
  ])->save();
}

/**
 * Menu utama: lima grup lalu Cari.
 */
function kh_setup_main_menu($groups) {
  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  foreach ($storage->loadByProperties(['menu_name' => 'main']) as $old_link) {
    $old_link->delete();
  }

  $items = [];
  foreach ($groups as $group) {
    $items[$group['label']] = '/' . $group['path'];
  }
  $items['Cari'] = '/cari';

  $weight = 0;
  foreach ($items as $title => $path) {
    MenuLinkContent::create([
      'title' => $title,
      'link' => ['uri' => 'internal:' . $path],
      'menu_name' => 'main',
      'weight' => $weight,
      'expanded' => FALSE,
    ])->save();
    $weight++;
  }
}

kh_setup_teasers($bundles);
kh_setup_search_index($bundles);
kh_setup_catalog_view($bundles, $groups);
kh_setup_related_view();
drupal_flush_all_caches();
kh_setup_facets($groups);
kh_setup_related_block($bundles);
kh_setup_main_menu($groups);
drupal_flush_all_caches();

echo "search ok\n";
