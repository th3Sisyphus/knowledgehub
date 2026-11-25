<?php

/**
 * Cek tema dan tampilan: beranda, halaman daftar, dan halaman konten
 * harus memuat bagian-bagian yang ada di prototipe HTML.
 * Mencetak OK bila lulus, selain itu daftar masalah.
 */

require_once __DIR__ . '/helpers.php';

$bad = [];

/**
 * Tambah masalah untuk tiap teks di $needles yang tidak ada di $html.
 */
function kh_theme_expect_all($html, $needles, $label, &$bad) {
  foreach ($needles as $needle) {
    if (strpos($html, $needle) === FALSE) {
      $bad[] = $label . ' tanpa: ' . $needle;
    }
  }
}

if (\Drupal::config('system.theme')->get('default') !== 'kh_theme') {
  $bad[] = 'tema default bukan kh_theme';
}

// ---- Beranda: hero, tombol mode gelap, Baru diperbarui, dan lima kartu area.
// Markup beranda dari modul kh_home (templates/kh-home.html.twig).
$home = kh_check_page('/', $bad);
kh_theme_expect_all($home, [
  'Temukan jawaban untuk',
  'id="theme-toggle"',
  'class="site-branding"',
  'class="hero blob-field"',
  'Baru diperbarui',
  'Jelajahi lima area',
], 'beranda', $bad);

$card_count = substr_count($home, 'class="area-card"');
if ($card_count !== 5) {
  $bad[] = 'beranda harus punya 5 kartu area, ada ' . $card_count;
}

// ---- Halaman daftar: teaser, sidebar facet, dan label jenis.
$list = kh_check_page('/prosedur', $bad);
if (substr_count($list, 'class="teaser') < 1) {
  $bad[] = '/prosedur tanpa teaser';
}
if (strpos($list, 'class="sidebar') === FALSE) {
  $bad[] = '/prosedur tanpa sidebar facet';
}
if (strpos($list, 'class="teaser-type"') === FALSE) {
  $bad[] = '/prosedur: label jenis di teaser hilang';
}

// ---- Halaman konten: kotak field, blok review, konten terkait, satu h1.
$node = kh_check_page('/prosedur/sop-perjalanan-dinas', $bad);
kh_theme_expect_all($node, [
  'class="fields"',
  'class="review"',
  'Konten terkait',
  'class="page-title"',
  'class="layout layout--node',
], 'halaman konten', $bad);

$heading_count = substr_count($node, '<h1');
if ($heading_count !== 1) {
  $bad[] = 'halaman konten harus punya satu h1, ada ' . $heading_count;
}
if (!preg_match('#<ol[^>]*>\s*<li>Isi Formulir#', $node)) {
  $bad[] = 'SOP: langkah harus berupa daftar bernomor';
}

// ---- FAQ: pertanyaan berupa heading.
$faq = kh_check_page('/bantuan/faq-perjalanan-dinas', $bad);
if (strpos($faq, '<h3') === FALSE) {
  $bad[] = 'FAQ: pertanyaan harus berupa heading';
}

kh_check_report($bad);
