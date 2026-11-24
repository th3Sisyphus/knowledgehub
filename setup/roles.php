<?php

/**
 * Workflow moderasi, role, izin, dan akun demo. Aman diulang.
 */

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;

const KH_BUNDLES = ['prosedur', 'referensi', 'pembelajaran', 'bantuan', 'alat_kerja'];

const KH_VOCABULARIES = [
  'departemen',
  'audience',
  'topik',
  'difficulty',
  'jenis_prosedur',
  'jenis_referensi',
  'jenis_pembelajaran',
  'jenis_bantuan',
  'jenis_alat_kerja',
];

/**
 * Buat workflow kh_editorial bila belum ada, lalu pasang ke lima content type.
 *
 * State draft dan published dibuat otomatis oleh Content Moderation.
 * Transisi bawaannya dibuang supaya hanya matriks spec yang berlaku.
 */
function kh_setup_workflow() {
  $workflow = Workflow::load('kh_editorial');

  if (!$workflow) {
    $workflow = Workflow::create([
      'id' => 'kh_editorial',
      'label' => 'Editorial Knowledge Hub',
      'type' => 'content_moderation',
    ]);
    $plugin = $workflow->getTypePlugin();

    $plugin->addState('in_review', 'In Review');
    $plugin->addState('archived', 'Archived');

    $plugin->deleteTransition('create_new_draft');
    $plugin->deleteTransition('publish');

    // id => [label, dari, ke]
    $transitions = [
      'save_draft' => ['Simpan Draft', ['draft'], 'draft'],
      'submit_review' => ['Kirim ke Review', ['draft'], 'in_review'],
      'revise' => ['Revisi konten terbit', ['published'], 'draft'],
      'approve' => ['Setujui (Publish)', ['in_review'], 'published'],
      'reject' => ['Kembalikan ke Draft', ['in_review'], 'draft'],
      'archive_review' => ['Arsipkan dari Review', ['in_review'], 'archived'],
      'archive' => ['Arsipkan', ['published'], 'archived'],
      'restore' => ['Pulihkan ke Draft', ['archived'], 'draft'],
    ];
    foreach ($transitions as $id => $transition) {
      list($label, $from, $to) = $transition;
      $plugin->addTransition($id, $label, $from, $to);
    }
  }

  $plugin = $workflow->getTypePlugin();
  foreach (KH_BUNDLES as $bundle) {
    $plugin->addEntityTypeAndBundle('node', $bundle);
  }
  $workflow->save();
}

/**
 * Buat tiga role bila belum ada. Super Admin mendapat semua akses.
 */
function kh_setup_roles() {
  $roles = [
    'super_admin' => 'Super Admin',
    'kh_admin' => 'Admin',
    'auditor' => 'Auditor',
  ];
  foreach ($roles as $id => $label) {
    if (!Role::load($id)) {
      Role::create(['id' => $id, 'label' => $label])->save();
    }
  }

  $super_admin = Role::load('super_admin');
  $super_admin->setIsAdmin(TRUE);
  $super_admin->save();
}

/**
 * Daftar nama izin transisi workflow.
 */
function kh_transition_permissions($transition_ids) {
  $permissions = [];
  foreach ($transition_ids as $id) {
    $permissions[] = 'use kh_editorial transition ' . $id;
  }
  return $permissions;
}

/**
 * Izin untuk role Admin: buat dan sunting konten sendiri, kelola term.
 */
function kh_admin_permissions() {
  $permissions = [
    'access content',
    'access content overview',
    'access navigation',
    'view the administration theme',
    'view latest version',
    'view own unpublished content',
    'access taxonomy overview',
    'view vocabulary labels',
  ];

  foreach (KH_BUNDLES as $bundle) {
    $permissions[] = "create $bundle content";
    $permissions[] = "edit own $bundle content";
    $permissions[] = "view $bundle revisions";
  }

  foreach (KH_VOCABULARIES as $vocabulary) {
    $permissions[] = "create terms in $vocabulary";
    $permissions[] = "edit terms in $vocabulary";
  }

  $transitions = ['save_draft', 'submit_review', 'revise'];
  return array_merge($permissions, kh_transition_permissions($transitions));
}

/**
 * Izin untuk role Auditor: lihat semua status, setujui atau kembalikan konten.
 */
function kh_auditor_permissions() {
  $permissions = [
    'access content',
    'access content overview',
    'access navigation',
    'view the administration theme',
    'view latest version',
    'view any unpublished content',
    'view all revisions',
  ];

  foreach (KH_BUNDLES as $bundle) {
    $permissions[] = "edit any $bundle content";
  }

  $transitions = ['approve', 'reject', 'archive_review', 'archive', 'restore'];
  return array_merge($permissions, kh_transition_permissions($transitions));
}

/**
 * Ganti semua izin sebuah role dengan daftar yang diberikan.
 */
function kh_set_permissions($role_id, $permissions) {
  $role = Role::load($role_id);
  foreach ($role->getPermissions() as $old_permission) {
    $role->revokePermission($old_permission);
  }
  foreach ($permissions as $permission) {
    $role->grantPermission($permission);
  }
  $role->save();
}

/**
 * Buat akun demo untuk tiap role (hanya untuk lokal).
 */
function kh_setup_demo_users() {
  $accounts = [
    'demo_superadmin' => 'super_admin',
    'demo_admin' => 'kh_admin',
    'demo_auditor' => 'auditor',
  ];

  foreach ($accounts as $name => $role_id) {
    $user = user_load_by_name($name);
    if (!$user) {
      $user = User::create([
        'name' => $name,
        'mail' => $name . '@example.invalid',
        'pass' => 'demo',
        'status' => 1,
      ]);
    }
    if (!$user->hasRole($role_id)) {
      $user->addRole($role_id);
    }
    $user->save();
  }
}

kh_setup_workflow();
kh_setup_roles();
kh_set_permissions('kh_admin', kh_admin_permissions());
kh_set_permissions('auditor', kh_auditor_permissions());
kh_setup_demo_users();

drupal_flush_all_caches();
echo "roles ok\n";
