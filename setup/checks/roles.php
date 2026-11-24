<?php

/**
 * Cek role, workflow, akses node, dan pengunci form Auditor.
 * Mencetak OK bila lulus, selain itu daftar masalah.
 */

require_once __DIR__ . '/helpers.php';

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;

const KH_ROLES_BUNDLES = ['prosedur', 'referensi', 'pembelajaran', 'bantuan', 'alat_kerja'];

// id transisi => [asal, tujuan, role pemegang].
const KH_ROLES_TRANSITIONS = [
  'save_draft' => [['draft'], 'draft', 'kh_admin'],
  'submit_review' => [['draft'], 'in_review', 'kh_admin'],
  'revise' => [['published'], 'draft', 'kh_admin'],
  'approve' => [['in_review'], 'published', 'auditor'],
  'reject' => [['in_review'], 'draft', 'auditor'],
  'archive_review' => [['in_review'], 'archived', 'auditor'],
  'archive' => [['published'], 'archived', 'auditor'],
  'restore' => [['archived'], 'draft', 'auditor'],
];

$bad = [];

/**
 * Id state asal sebuah transisi, contoh ['draft'].
 */
function kh_roles_from_states($transition) {
  $ids = [];
  foreach ($transition->from() as $state) {
    $ids[] = $state->id();
  }
  return $ids;
}

/**
 * Workflow ada, aktif di lima content type, dan transisinya sesuai matriks spec.
 * Mengembalikan FALSE bila workflow tidak ada (cek lain tidak berarti lagi).
 */
function kh_roles_check_workflow(&$bad) {
  $workflow = Workflow::load('kh_editorial');
  if (!$workflow) {
    $bad[] = 'workflow kh_editorial tidak ada';
    return FALSE;
  }
  $plugin = $workflow->getTypePlugin();

  foreach (KH_ROLES_BUNDLES as $bundle) {
    if (!in_array($bundle, $plugin->getBundlesForEntityType('node'))) {
      $bad[] = 'moderasi tidak aktif di ' . $bundle;
    }
  }

  $roles = Role::loadMultiple();
  foreach (KH_ROLES_TRANSITIONS as $id => $definition) {
    list($from, $to, $holder) = $definition;

    if (!$plugin->hasTransition($id)) {
      $bad[] = 'transisi ' . $id . ' tidak ada';
      continue;
    }

    $transition = $plugin->getTransition($id);
    if ($transition->to()->id() !== $to) {
      $bad[] = 'transisi ' . $id . ' menuju ' . $transition->to()->id();
    }
    if (kh_roles_from_states($transition) !== $from) {
      $bad[] = 'transisi ' . $id . ' asal salah';
    }

    // Hanya role pemegang yang boleh memakai transisi ini.
    foreach (['kh_admin', 'auditor'] as $role_id) {
      $has_permission = isset($roles[$role_id])
        && $roles[$role_id]->hasPermission('use kh_editorial transition ' . $id);
      $should_have = ($role_id === $holder);
      if ($has_permission !== $should_have) {
        $bad[] = 'izin transisi ' . $id . ' salah di role ' . $role_id;
      }
    }
  }

  return TRUE;
}

/**
 * Izin Admin: buat dan edit konten sendiri, kelola term, tanpa hak berlebih.
 */
function kh_roles_check_admin_permissions($role, &$bad) {
  foreach (KH_ROLES_BUNDLES as $bundle) {
    if (!$role->hasPermission('create ' . $bundle . ' content')) {
      $bad[] = 'kh_admin tanpa create ' . $bundle;
    }
    if (!$role->hasPermission('edit own ' . $bundle . ' content')) {
      $bad[] = 'kh_admin tanpa edit own ' . $bundle;
    }
    if ($role->hasPermission('edit any ' . $bundle . ' content')) {
      $bad[] = 'kh_admin punya edit any ' . $bundle;
    }
  }
  if ($role->hasPermission('view any unpublished content')) {
    $bad[] = 'kh_admin melihat draft orang lain';
  }
  if (!$role->hasPermission('create terms in topik')) {
    $bad[] = 'kh_admin tanpa create terms in topik';
  }
}

/**
 * Izin Auditor: lihat semua status dan edit konten, tanpa membuat konten.
 */
function kh_roles_check_auditor_permissions($role, &$bad) {
  foreach (KH_ROLES_BUNDLES as $bundle) {
    if ($role->hasPermission('create ' . $bundle . ' content')) {
      $bad[] = 'auditor punya create ' . $bundle;
    }
    if (!$role->hasPermission('edit any ' . $bundle . ' content')) {
      $bad[] = 'auditor tanpa edit any ' . $bundle;
    }
  }
  if (!$role->hasPermission('view any unpublished content')) {
    $bad[] = 'auditor tidak bisa lihat semua status';
  }
  if ($role->hasPermission('create terms in topik')) {
    $bad[] = 'auditor boleh kelola term';
  }
}

/**
 * Izin tiga role: Super Admin penuh, Admin dan Auditor sesuai spec,
 * dan tidak ada yang memegang hak konfigurasi.
 */
function kh_roles_check_permissions(&$bad) {
  $roles = Role::loadMultiple();

  if (!isset($roles['super_admin']) || !$roles['super_admin']->isAdmin()) {
    $bad[] = 'super_admin harus is_admin';
  }

  if (!isset($roles['kh_admin']) || !isset($roles['auditor'])) {
    $bad[] = 'role kh_admin atau auditor tidak ada';
  }
  else {
    kh_roles_check_admin_permissions($roles['kh_admin'], $bad);
    kh_roles_check_auditor_permissions($roles['auditor'], $bad);

    $forbidden = [
      'administer modules',
      'administer site configuration',
      'administer permissions',
      'administer nodes',
    ];
    foreach ($forbidden as $permission) {
      foreach (['kh_admin', 'auditor'] as $role_id) {
        if ($roles[$role_id]->hasPermission($permission)) {
          $bad[] = $role_id . ' punya ' . $permission;
        }
      }
    }
  }

  $anonymous = Role::load('anonymous');
  $sees_hidden = $anonymous->hasPermission('view any unpublished content')
    || $anonymous->hasPermission('view latest version');
  if ($sees_hidden) {
    $bad[] = 'anonim melihat non-terbit';
  }
}

/**
 * Buat salinan SOP Perjalanan Dinas dengan judul, id asal, status, dan pemilik tertentu.
 * Salinan ini harus dihapus oleh pemanggil.
 */
function kh_roles_make_test_node($source, $slug, $state, $owner_id) {
  $copy = $source->createDuplicate();
  $copy->set('title', 'Uji ' . $slug);
  $copy->set('field_slug_asal', $slug);
  $copy->set('moderation_state', $state);
  $copy->setOwnerId($owner_id);
  $copy->save();
  return $copy;
}

/**
 * Akses lihat: anonim hanya melihat Published, Auditor melihat semua status.
 */
function kh_roles_check_view_access($accounts, $draft, $published, &$bad) {
  $anonymous = $accounts['anonymous'];
  $auditor = $accounts['auditor'];
  $admin = $accounts['admin'];

  if (!$draft || $draft->access('view', $anonymous)) {
    $bad[] = 'anonim bisa lihat Draft';
  }
  if (!$published || !$published->access('view', $anonymous)) {
    $bad[] = 'anonim tidak bisa lihat Published';
  }

  if ($draft && $draft->get('moderation_state')->value !== 'draft') {
    $bad[] = 'sop-lembur bukan draft';
  }
  if ($published && $published->get('moderation_state')->value !== 'published') {
    $bad[] = 'sop-perjalanan-dinas bukan published';
  }

  if ($auditor && $draft && !$draft->access('view', $auditor)) {
    $bad[] = 'auditor tidak bisa lihat Draft';
  }
  if ($admin && $published && !$published->access('view', $admin)) {
    $bad[] = 'admin tidak bisa lihat Published';
  }

  // Konten Archived: anonim tidak boleh, Auditor boleh.
  if ($published) {
    $archived = kh_roles_make_test_node($published, 'uji-arsip', 'archived', 1);
    if ($archived->access('view', $anonymous)) {
      $bad[] = 'anonim bisa lihat Archived';
    }
    if ($auditor && !$archived->access('view', $auditor)) {
      $bad[] = 'auditor tidak bisa lihat Archived';
    }
    $archived->delete();
  }
}

/**
 * Akses edit: Auditor hanya lewat In Review, Admin hanya konten miliknya.
 * Mengembalikan node In Review uji (untuk cek form), yang harus dihapus pemanggil.
 */
function kh_roles_check_edit_access($accounts, $draft, $published, &$bad) {
  $auditor = $accounts['auditor'];
  $admin = $accounts['admin'];

  // Auditor tidak punya transisi dari Draft, jadi tidak bisa mengedit Draft.
  if ($auditor && $draft && $draft->access('update', $auditor)) {
    $bad[] = 'auditor bisa edit Draft (tanpa transisi)';
  }

  $in_review = NULL;
  if ($published) {
    $in_review = kh_roles_make_test_node($published, 'uji-review', 'in_review', 1);
    if ($auditor && !$in_review->access('update', $auditor)) {
      $bad[] = 'auditor tidak bisa buka form edit In Review';
    }
  }

  // Admin tidak boleh melihat atau mengedit Draft milik pengguna lain (uid 1).
  if ($admin && $published) {
    $others = kh_roles_make_test_node($published, 'uji-milik-lain', 'draft', 1);
    if ($others->access('view', $admin)) {
      $bad[] = 'admin lihat Draft milik orang lain';
    }
    if ($others->access('update', $admin)) {
      $bad[] = 'admin edit konten milik orang lain';
    }
    $others->delete();
  }

  // Draft seed dimiliki demo_admin, jadi boleh diedit.
  if ($admin && $draft && !$draft->access('update', $admin)) {
    $bad[] = 'admin tidak bisa mengedit Draft miliknya (seed)';
  }

  return $in_review;
}

/**
 * Bangun form edit sebagai pengguna tertentu.
 */
function kh_roles_build_form($node, $account) {
  $switcher = \Drupal::service('account_switcher');
  $switcher->switchTo($account);
  $form = \Drupal::service('entity.form_builder')->getForm($node, 'default');
  $switcher->switchBack();
  return $form;
}

/**
 * Form Auditor: isi terkunci, status moderasi terbuka. Super Admin tidak dikunci.
 */
function kh_roles_check_auditor_form($accounts, $in_review, &$bad) {
  $form = kh_roles_build_form($in_review, $accounts['auditor']);

  foreach (['title', 'field_ringkasan', 'field_jenis', 'body'] as $element) {
    if (empty($form[$element]['#disabled'])) {
      $bad[] = 'form auditor: ' . $element . ' tidak terkunci';
    }
  }
  if (!isset($form['moderation_state']) || !empty($form['moderation_state']['#disabled'])) {
    $bad[] = 'form auditor: moderation_state terkunci atau hilang';
  }

  $super_form = kh_roles_build_form($in_review, $accounts['super_admin']);
  if (!empty($super_form['title']['#disabled'])) {
    $bad[] = 'form super admin terkunci';
  }
}

if (kh_roles_check_workflow($bad)) {
  kh_roles_check_permissions($bad);

  $accounts = [
    'anonymous' => User::getAnonymousUser(),
    'auditor' => user_load_by_name('demo_auditor'),
    'admin' => user_load_by_name('demo_admin'),
    'super_admin' => user_load_by_name('demo_superadmin'),
  ];
  if (!$accounts['auditor'] || !$accounts['admin'] || !$accounts['super_admin']) {
    $bad[] = 'akun demo belum lengkap';
  }

  $draft = kh_check_node('sop-lembur');
  $published = kh_check_node('sop-perjalanan-dinas');

  kh_roles_check_view_access($accounts, $draft, $published, $bad);
  $in_review = kh_roles_check_edit_access($accounts, $draft, $published, $bad);

  $accounts_ready = $accounts['auditor'] && $accounts['super_admin'];
  if ($accounts_ready && $in_review) {
    kh_roles_check_auditor_form($accounts, $in_review, $bad);
  }
  if ($in_review) {
    $in_review->delete();
  }
}

kh_check_report($bad);
