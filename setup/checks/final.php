<?php

/**
 * Pemeriksaan hasil review akhir:
 * 1. Impor ulang tidak menimpa edit.
 * 2. Ganti judul tidak mematikan alamat lama.
 * 3. Admin bisa bekerja pada konten seed.
 * 4. Kueri pendek mempersempit hasil.
 * 5. Admin dan Auditor punya akses navigasi.
 *
 * Mencetak OK bila lulus, selain itu daftar masalah.
 */

require_once __DIR__ . '/helpers.php';

$bad = [];
$nodes = \Drupal::entityTypeManager()->getStorage('node');
$aliases = \Drupal::entityTypeManager()->getStorage('path_alias');

/**
 * 1. Impor ulang tidak mengubah status Archived atau judul yang sudah diedit.
 */
function kh_check_reimport(&$bad) {
  $nodes = \Drupal::entityTypeManager()->getStorage('node');
  $node = kh_check_node('sop-pengajuan-cuti');
  $original_title = $node->label();

  $node->set('title', 'Judul diedit Auditor');
  $node->set('moderation_state', 'archived');
  $node->save();

  // Jalankan impor lagi. Keluarannya tidak perlu ditampilkan.
  ob_start();
  include '/opt/drupal/setup/import_content.php';
  ob_end_clean();

  $node = $nodes->loadUnchanged($node->id());
  if ($node->get('moderation_state')->value !== 'archived') {
    $bad[] = 'impor ulang mengubah status Archived: ' . $node->get('moderation_state')->value;
  }
  if ($node->label() !== 'Judul diedit Auditor') {
    $bad[] = 'impor ulang menimpa judul yang diedit';
  }

  // Kembalikan seperti semula.
  $node->set('title', $original_title);
  $node->set('moderation_state', 'published');
  $node->save();
}

/**
 * 2. Ganti judul: alamat lama tetap terbuka (Pathauto menyimpan alias lama).
 */
function kh_check_old_url_survives(&$bad) {
  $aliases = \Drupal::entityTypeManager()->getStorage('path_alias');
  $node = kh_check_node('sop-perjalanan-dinas');
  $old_alias = kh_check_alias($node);
  $original_title = $node->label();

  $node->set('title', 'SOP Perjalanan Dinas Baru');
  $node->save();

  $status = kh_check_request($old_alias)->getStatusCode();
  if ($status !== 200 && $status !== 301) {
    $bad[] = 'URL lama ' . $old_alias . ' setelah ganti judul: ' . $status;
  }

  // Kembalikan judul, lalu rapikan alias yang dibuat oleh pengujian ini.
  $node->set('title', $original_title);
  $node->save();

  $temporary = $aliases->loadByProperties(['alias' => '/prosedur/sop-perjalanan-dinas-baru']);
  foreach ($temporary as $alias) {
    $alias->delete();
  }

  // Alias asli dibuat ulang saat judul dikembalikan. Sisakan satu.
  $same = $aliases->loadByProperties(['path' => '/node/' . $node->id(), 'alias' => $old_alias]);
  $kept_one = FALSE;
  foreach ($same as $alias) {
    if ($kept_one) {
      $alias->delete();
    }
    $kept_one = TRUE;
  }
}

/**
 * 3. demo_admin boleh mengedit konten seed (Published dan Draft).
 */
function kh_check_admin_can_edit_seed(&$bad) {
  $admin = user_load_by_name('demo_admin');
  $published = kh_check_node('sop-perjalanan-dinas');
  $draft = kh_check_node('sop-lembur');

  if ($admin && !$published->access('update', $admin)) {
    $bad[] = 'demo_admin tidak bisa mengedit konten Published seed';
  }
  if ($admin && !$draft->access('update', $admin)) {
    $bad[] = 'demo_admin tidak bisa mengedit Draft seed';
  }
}

/**
 * 4. Kueri dua huruf ("IT") menghasilkan lebih sedikit artikel dari semua konten.
 */
function kh_check_short_query(&$bad) {
  $filtered = (string) kh_check_request('/cari?search_api_fulltext=IT')->getBody();
  $everything = (string) kh_check_request('/cari')->getBody();

  $filtered_count = substr_count($filtered, '<article');
  $everything_count = substr_count($everything, '<article');

  if ($everything_count > 0 && $filtered_count >= $everything_count) {
    $bad[] = 'kueri "IT" tidak mempersempit hasil';
  }
}

/**
 * 5. Admin dan Auditor punya izin yang dibutuhkan untuk membuka panel admin.
 */
function kh_check_navigation_permissions(&$bad) {
  $required = [
    'access navigation',
    'access content overview',
    'view the administration theme',
  ];

  foreach (['kh_admin', 'auditor'] as $role_id) {
    $role = \Drupal\user\Entity\Role::load($role_id);
    foreach ($required as $permission) {
      if (!$role->hasPermission($permission)) {
        $bad[] = $role_id . " tanpa izin '" . $permission . "'";
      }
    }
  }
}

kh_check_reimport($bad);
kh_check_old_url_survives($bad);
kh_check_admin_can_edit_seed($bad);
kh_check_short_query($bad);
kh_check_navigation_permissions($bad);

kh_check_report($bad);
