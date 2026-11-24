<?php

/**
 * Bentuk isi (HTML) per jenis konten dari satu entri catalog.json.
 * Dipakai oleh import_content.php dan format_body.php.
 */

/**
 * Escape teks supaya aman dipakai di HTML.
 */
function kh_escape($text) {
  return htmlspecialchars($text, ENT_NOQUOTES);
}

/**
 * Bungkus tiap item dengan <li>, lalu bungkus semuanya dengan <ul> atau <ol>.
 */
function kh_list($items, $tag) {
  $html = '';
  foreach ($items as $item) {
    $html .= '<li>' . $item . '</li>';
  }
  return '<' . $tag . '>' . $html . '</' . $tag . '>';
}

/**
 * Escape semua baris teks.
 */
function kh_escape_all($rows) {
  $result = [];
  foreach ($rows as $row) {
    $result[] = kh_escape($row);
  }
  return $result;
}

/**
 * Pecah satu baris menjadi dua bagian di pemisah pertama.
 * Bagian kedua kosong bila pemisah tidak ada.
 */
function kh_split($row, $separator) {
  $parts = explode($separator, $row, 2);
  if (count($parts) < 2) {
    $parts[] = '';
  }
  return $parts;
}

/**
 * Isi Video Tutorial: "00:00 Judul bab" menjadi waktu tebal lalu judul.
 */
function kh_body_video($rows) {
  $items = [];
  foreach ($rows as $row) {
    list($time, $title) = kh_split($row, ' ');
    $items[] = '<strong>' . kh_escape($time) . '</strong> ' . kh_escape($title);
  }
  return '<h2>Bab video</h2>' . kh_list($items, 'ul');
}

/**
 * Isi FAQ: "Pertanyaan|Jawaban" menjadi heading lalu paragraf.
 */
function kh_body_faq($rows) {
  $html = '';
  foreach ($rows as $row) {
    list($question, $answer) = kh_split($row, '|');
    $html .= '<h3>' . kh_escape($question) . '</h3>';
    $html .= '<p>' . kh_escape($answer) . '</p>';
  }
  return $html;
}

/**
 * Isi Troubleshooting: "Langkah|Penjelasan" menjadi daftar bernomor.
 */
function kh_body_troubleshooting($rows) {
  $items = [];
  foreach ($rows as $row) {
    list($step, $detail) = kh_split($row, '|');
    $items[] = '<strong>' . kh_escape($step) . '</strong><br>' . kh_escape($detail);
  }
  return '<h2>Coba langkah ini berurutan</h2>' . kh_list($items, 'ol');
}

/**
 * Isi Glossary: "Istilah: arti" menjadi daftar definisi.
 */
function kh_body_glossary($rows) {
  $html = '';
  foreach ($rows as $row) {
    list($term, $meaning) = kh_split($row, ': ');
    $html .= '<dt>' . kh_escape($term) . '</dt>';
    $html .= '<dd>' . kh_escape($meaning) . '</dd>';
  }
  return '<dl>' . $html . '</dl>';
}

/**
 * Isi umum: baris "## Judul" menjadi heading, baris lain menjadi paragraf.
 */
function kh_body_prose($rows) {
  $html = '';
  foreach ($rows as $row) {
    if (strpos($row, '## ') === 0) {
      $html .= '<h2>' . kh_escape(substr($row, 3)) . '</h2>';
    }
    else {
      $html .= '<p>' . kh_escape($row) . '</p>';
    }
  }
  return $html;
}

/**
 * Bentuk isi sesuai jenis konten.
 */
function kh_body(array $entry) {
  $rows = [];
  if (isset($entry['body'])) {
    $rows = $entry['body'];
  }

  switch ($entry['type']) {
    case 'SOP':
      return '<h2>Langkah</h2>' . kh_list(kh_escape_all($rows), 'ol');

    case 'Checklist':
      return kh_list(kh_escape_all($rows), 'ul');

    case 'Tutorial':
      return kh_list(kh_escape_all($rows), 'ol');

    case 'Video Tutorial':
      return kh_body_video($rows);

    case 'FAQ':
      return kh_body_faq($rows);

    case 'Troubleshooting':
      return kh_body_troubleshooting($rows);

    case 'Glossary':
      return kh_body_glossary($rows);

    case 'Template':
      return '<h2>Kolom yang diminta</h2>' . kh_list(kh_escape_all($rows), 'ul');

    default:
      return kh_body_prose($rows);
  }
}
