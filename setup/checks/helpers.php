<?php

/**
 * Fungsi bantu bersama untuk semua skrip di setup/checks/.
 *
 * Tiap skrip cek mengumpulkan masalah dalam daftar $bad lalu mencetak
 * "OK" bila daftar kosong, atau isi daftar bila ada masalah.
 */

/**
 * Cetak hasil akhir sebuah cek.
 */
function kh_check_report($bad) {
  if (empty($bad)) {
    echo "OK\n";
    return;
  }
  echo implode("\n", $bad) . "\n";
}

/**
 * Ambil satu halaman dari situs ini lewat HTTP sebagai pengunjung anonim.
 * Mengembalikan response Guzzle. Tidak mengikuti redirect dan tidak melempar error HTTP.
 */
function kh_check_request($path) {
  $client = \Drupal::httpClient();
  return $client->get('http://localhost' . $path, [
    'http_errors' => FALSE,
    'allow_redirects' => FALSE,
  ]);
}

/**
 * Isi halaman sebagai teks. Kosong bila status bukan 200 (dan alasannya dicatat di $bad).
 */
function kh_check_page($path, &$bad) {
  $response = kh_check_request($path);
  if ($response->getStatusCode() !== 200) {
    $bad[] = $path . ' status ' . $response->getStatusCode();
    return '';
  }
  return (string) $response->getBody();
}

/**
 * Muat node seed dari id asalnya di data.js, contoh 'sop-perjalanan-dinas'.
 * Mengembalikan NULL bila tidak ada.
 */
function kh_check_node($slug) {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $found = $storage->loadByProperties(['field_slug_asal' => $slug]);
  if (empty($found)) {
    return NULL;
  }
  return reset($found);
}

/**
 * Alamat alias sebuah node, atau string kosong bila node tidak ada.
 */
function kh_check_alias($node) {
  if (!$node) {
    return '';
  }
  $manager = \Drupal::service('path_alias.manager');
  return $manager->getAliasByPath('/node/' . $node->id());
}
