<?php

namespace Drupal\kh_home\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Beranda Knowledge Hub: hero, kartu 5 area, info terbaru, pintasan.
 */
class HomeController extends ControllerBase {

  /**
   * Lima area utama.
   *
   * Kunci = content type. Isi: path, nama, deskripsi, daftar jenis, kelas CSS.
   */
  const AREAS = [
    'prosedur' => [
      'path' => '/prosedur',
      'label' => 'Prosedur',
      'description' => 'Langkah terstruktur yang harus diikuti untuk menyelesaikan tugas.',
      'types' => 'SOP · Panduan Kerja · Checklist',
      'css' => 'prosedur',
    ],
    'referensi' => [
      'path' => '/referensi',
      'label' => 'Referensi',
      'description' => 'Informasi untuk dipahami atau dirujuk, bukan instruksi langkah demi langkah.',
      'types' => 'Knowledge Article · Kebijakan · Glossary · Studi Kasus',
      'css' => 'referensi',
    ],
    'pembelajaran' => [
      'path' => '/pembelajaran',
      'label' => 'Pembelajaran',
      'description' => 'Materi belajar berurutan, terutama untuk pegawai baru.',
      'types' => 'Tutorial · Video Tutorial',
      'css' => 'pembelajaran',
    ],
    'bantuan' => [
      'path' => '/bantuan',
      'label' => 'Bantuan',
      'description' => 'Jawaban saat ada pertanyaan atau hambatan kerja.',
      'types' => 'FAQ · Troubleshooting',
      'css' => 'bantuan',
    ],
    'alat_kerja' => [
      'path' => '/alat-kerja',
      'label' => 'Alat Kerja',
      'description' => 'Dokumen siap diisi yang langsung dipakai dalam pekerjaan.',
      'types' => 'Template',
      'css' => 'alat-kerja',
    ],
  ];

  const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
  ];

  /**
   * Tanggal Indonesia, contoh "28 Oktober 2026".
   */
  private function formatDate($ymd) {
    $date = new \DateTimeImmutable($ymd);
    $month_name = self::MONTHS[(int) $date->format('n') - 1];
    return $date->format('j') . ' ' . $month_name . ' ' . $date->format('Y');
  }

  /**
   * Id term dari vocabulary dan nama, atau NULL bila tidak ada.
   */
  private function findTermId($vocabulary, $name) {
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $found = $storage->loadByProperties(['vid' => $vocabulary, 'name' => $name]);
    if (empty($found)) {
      return NULL;
    }
    $term = reset($found);
    return (int) $term->id();
  }

  /**
   * Query dasar: hanya konten Published yang boleh dilihat pengunjung.
   */
  private function publishedQuery() {
    $storage = $this->entityTypeManager()->getStorage('node');
    return $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1);
  }

  /**
   * HTML kartu untuk lima area, lengkap dengan jumlah konten.
   */
  private function buildCards() {
    $html = '';
    $number = 0;

    foreach (self::AREAS as $bundle => $area) {
      $number++;
      $count = $this->publishedQuery()
        ->condition('type', $bundle)
        ->count()
        ->execute();

      $html .= '<article class="card c-' . $area['css'] . '">';
      $html .= '<span class="card-num" aria-hidden="true">0' . $number . '</span>';
      $html .= '<h3><a href="' . $area['path'] . '">' . Html::escape($area['label']) . '</a></h3>';
      $html .= '<p>' . Html::escape($area['description']) . '</p>';
      $html .= '<p class="card-types">' . Html::escape($area['types']) . '</p>';
      $html .= '<p class="card-count">' . $count . ' konten</p>';
      $html .= '</article>';
    }

    return $html;
  }

  /**
   * HTML daftar Info Terbaru: empat konten dengan tanggal terbaru.
   *
   * Bila tanggal sama, judul menentukan urutan supaya daftar tidak berganti-ganti.
   */
  private function buildNews() {
    $ids = $this->publishedQuery()
      ->condition('type', array_keys(self::AREAS), 'IN')
      ->condition('field_tanggal', '', '<>')
      ->sort('field_tanggal', 'DESC')
      ->sort('title', 'ASC')
      ->range(0, 4)
      ->execute();

    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($ids);

    $html = '';
    foreach ($nodes as $node) {
      $date = $node->get('field_tanggal')->value;
      $html .= '<li>';
      $html .= '<time datetime="' . $date . '">' . $this->formatDate($date) . '</time> ';
      $html .= '<a href="' . $node->toUrl()->toString() . '">' . Html::escape($node->label()) . '</a>';
      $html .= '</li>';
    }
    return $html;
  }

  /**
   * HTML pintasan "Mulai dari kebutuhan Anda".
   */
  private function buildShortcuts() {
    $html = '';

    $new_employee = $this->findTermId('audience', 'Pegawai Baru');
    $basic = $this->findTermId('difficulty', 'Dasar');
    if ($new_employee && $basic) {
      $html .= '<li><a href="/pembelajaran?f%5B0%5D=audience%3A' . $new_employee;
      $html .= '&amp;f%5B1%5D=difficulty%3A' . $basic . '">Pegawai baru: materi dasar</a></li>';
    }

    $troubleshooting = $this->findTermId('jenis_bantuan', 'Troubleshooting');
    if ($troubleshooting) {
      $html .= '<li><a href="/bantuan?f%5B0%5D=jenis%3A' . $troubleshooting;
      $html .= '">Ada kendala sistem: Troubleshooting</a></li>';
    }

    $html .= '<li><a href="/cari">Cari semua konten</a></li>';
    return $html;
  }

  /**
   * HTML bagian hero dengan kotak pencarian.
   */
  private function buildHero() {
    $search_url = Url::fromRoute('view.kh_katalog.page_cari')->toString();

    $html = '<div class="hero">';
    $html .= '<h1>Temukan cara kerja tanpa harus hafal nama dokumen</h1>';
    $html .= '<p>SOP, kebijakan, tutorial, FAQ, dan template untuk satu proses ada di satu tempat.</p>';
    $html .= '<form class="hero-search" action="' . $search_url . '" method="get" role="search">';
    $html .= '<label for="q-hero" class="visually-hidden">Cari di Knowledge Hub</label>';
    $html .= '<input id="q-hero" name="search_api_fulltext" type="search" ';
    $html .= 'placeholder="Contoh: Bagaimana mengajukan perjalanan dinas?">';
    $html .= '<button class="button button--primary" type="submit">Cari</button>';
    $html .= '</form></div>';
    return $html;
  }

  /**
   * Halaman beranda.
   */
  public function page() {
    $html = $this->buildHero();
    $html .= '<h2 class="section-title">5 Area Utama</h2>';
    $html .= '<div class="cards">' . $this->buildCards() . '</div>';
    $html .= '<div class="two-col">';
    $html .= '<div><h2 class="section-title">Info Terbaru</h2>';
    $html .= '<ul class="news">' . $this->buildNews() . '</ul></div>';
    $html .= '<div><h2 class="section-title">Mulai dari kebutuhan Anda</h2>';
    $html .= '<ul class="role-links">' . $this->buildShortcuts() . '</ul></div>';
    $html .= '</div>';

    return [
      '#markup' => Markup::create($html),
      '#cache' => [
        'tags' => ['node_list', 'taxonomy_term_list'],
        'contexts' => ['user.permissions'],
      ],
    ];
  }

}
