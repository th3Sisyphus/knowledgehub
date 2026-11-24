<?php

/**
 * Cek halaman daftar, facet, pencarian, menu, dan konten terkait.
 * Mencetak OK bila lulus, selain itu daftar masalah.
 */

require_once __DIR__ . '/helpers.php';

$bad = [];

/**
 * Tambah masalah bila teks $haystack tidak memuat $needle.
 */
function kh_expect_contains($haystack, $needle, $message, &$bad) {
  if (strpos($haystack, $needle) === FALSE) {
    $bad[] = $message;
  }
}

/**
 * Tambah masalah bila teks $haystack memuat $needle.
 */
function kh_expect_missing($haystack, $needle, $message, &$bad) {
  if (strpos($haystack, $needle) !== FALSE) {
    $bad[] = $message;
  }
}

// ---- Prosedur: facet Departemen, Audience, Topik, Jenis. Draft tidak tampil.
$prosedur = kh_check_page('/prosedur', $bad);
kh_expect_contains($prosedur, 'SOP Perjalanan Dinas', '/prosedur tanpa SOP Perjalanan Dinas', $bad);
kh_expect_missing($prosedur, 'SOP Lembur', '/prosedur menampilkan Draft SOP Lembur', $bad);
foreach (['Departemen', 'Audience', 'Topik', 'Jenis'] as $facet) {
  kh_expect_contains($prosedur, $facet, '/prosedur tanpa facet ' . $facet, $bad);
}
kh_expect_missing($prosedur, 'Difficulty', '/prosedur punya facet Difficulty', $bad);

// ---- Pembelajaran: punya facet Difficulty.
$belajar = kh_check_page('/pembelajaran', $bad);
kh_expect_contains($belajar, 'Difficulty', '/pembelajaran tanpa facet Difficulty', $bad);

// ---- Bantuan: hanya Departemen, Topik, Jenis.
$bantuan = kh_check_page('/bantuan', $bad);
kh_expect_missing($bantuan, 'Difficulty', '/bantuan punya facet Difficulty', $bad);
kh_expect_missing($bantuan, 'Audience', '/bantuan punya facet Audience', $bad);
kh_expect_contains($bantuan, 'Departemen', '/bantuan tanpa facet Departemen', $bad);

// ---- Alat Kerja: hanya Departemen.
$alat = kh_check_page('/alat-kerja', $bad);
kh_expect_contains($alat, 'Departemen', '/alat-kerja tanpa facet Departemen', $bad);
foreach (['Audience', 'Topik', 'Difficulty'] as $facet) {
  kh_expect_missing($alat, $facet, '/alat-kerja punya facet ' . $facet, $bad);
}

// ---- Pencarian.
$cari = kh_check_page('/cari?search_api_fulltext=cuti', $bad);
kh_expect_contains($cari, 'SOP Pengajuan Cuti', '/cari?cuti tanpa SOP Pengajuan Cuti', $bad);

$kosong = kh_check_page('/cari?search_api_fulltext=zzzzqqq', $bad);
if ($kosong !== '') {
  kh_expect_contains($kosong, 'Tidak ada hasil', '/cari tanpa hasil harus menampilkan "Tidak ada hasil"', $bad);
}

// ---- Konten terkait di halaman konten: ada blok dan ada tautan di dalamnya.
$sop = kh_check_page('/prosedur/sop-perjalanan-dinas', $bad);
$position = strpos($sop, 'Konten terkait');
if ($position === FALSE) {
  $bad[] = 'node tanpa blok Konten terkait';
}
else {
  $block_html = substr($sop, $position, 4000);
  $has_link = preg_match('#href="/(prosedur|referensi|pembelajaran|bantuan|alat-kerja)/#', $block_html);
  if (!$has_link) {
    $bad[] = 'blok Konten terkait tanpa tautan';
  }
}

// ---- Menu utama berisi lima grup dan Cari.
$home = kh_check_page('/', $bad);
$menu_paths = ['/prosedur', '/referensi', '/pembelajaran', '/bantuan', '/alat-kerja', '/cari'];
foreach ($menu_paths as $path) {
  kh_expect_contains($home, 'href="' . $path . '"', 'menu utama tanpa ' . $path, $bad);
}

// ---- Klik facet harus menyaring: jumlah hasil dengan filter Jenis=Checklist
// lebih sedikit dari semua, dan lebih dari nol.
$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$found = $terms->loadByProperties(['vid' => 'jenis_prosedur', 'name' => 'Checklist']);
$checklist = reset($found);

$total = substr_count($prosedur, '<article');
$filtered_page = kh_check_page('/prosedur?f%5B0%5D=jenis%3A' . $checklist->id(), $bad);
$filtered = substr_count($filtered_page, '<article');
if ($filtered === 0 || $filtered >= $total) {
  $bad[] = 'facet Jenis tidak menyaring (' . $filtered . ' dari ' . $total . ')';
}

kh_check_report($bad);
