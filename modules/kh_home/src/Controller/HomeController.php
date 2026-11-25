<?php

namespace Drupal\kh_home\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Beranda Knowledge Hub: hero pencarian, kartu lima area, konten terbaru,
 * pertanyaan populer, dan banner bantuan.
 */
class HomeController extends ControllerBase {

  /**
   * Lima area utama.
   *
   * Kunci = content type. Isi: path, nama, deskripsi, daftar jenis, key ikon.
   */
  const AREAS = [
    'prosedur' => [
      'path' => '/prosedur',
      'label' => 'Prosedur',
      'description' => 'Langkah terstruktur yang harus diikuti untuk menyelesaikan tugas.',
      'types' => 'SOP · Panduan Kerja · Checklist',
      'icon' => 'steps',
    ],
    'referensi' => [
      'path' => '/referensi',
      'label' => 'Referensi',
      'description' => 'Informasi untuk dipahami atau dirujuk, bukan instruksi langkah demi langkah.',
      'types' => 'Knowledge Article · Kebijakan · Glossary',
      'icon' => 'book',
    ],
    'pembelajaran' => [
      'path' => '/pembelajaran',
      'label' => 'Pembelajaran',
      'description' => 'Materi belajar berurutan, terutama untuk pegawai baru.',
      'types' => 'Tutorial · Video Tutorial',
      'icon' => 'play',
    ],
    'bantuan' => [
      'path' => '/bantuan',
      'label' => 'Bantuan',
      'description' => 'Jawaban saat ada pertanyaan atau hambatan kerja.',
      'types' => 'FAQ · Troubleshooting',
      'icon' => 'help',
    ],
    'alat_kerja' => [
      'path' => '/alat-kerja',
      'label' => 'Alat Kerja',
      'description' => 'Dokumen siap diisi yang langsung dipakai dalam pekerjaan.',
      'types' => 'Template',
      'icon' => 'doc',
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
   * URL daftar konten dengan filter facet opsional.
   *
   * $facets = [['audience', 12], ['difficulty', 3]] menghasilkan ?f[0]=audience:12&f[1]=difficulty:3.
   */
  private function filteredUrl($path, array $facets = []) {
    $query = [];
    foreach ($facets as $i => $facet) {
      $query['f[' . $i . ']'] = $facet[0] . ':' . $facet[1];
    }
    return $path . ($query ? '?' . http_build_query($query, '', '&') : '');
  }

  /**
   * Data kartu lima area, lengkap dengan jumlah konten.
   *
   * Tiap item: label, url, description, types, icon (nama ikon), count.
   */
  private function buildAreas() {
    $areas = [];
    foreach (self::AREAS as $bundle => $area) {
      $count = $this->publishedQuery()
        ->condition('type', $bundle)
        ->count()
        ->execute();

      $areas[] = [
        'label' => $area['label'],
        'url' => $area['path'],
        'description' => $area['description'],
        'types' => $area['types'],
        'icon' => $area['icon'],
        'count' => (int) $count,
      ];
    }
    return $areas;
  }

  /**
   * Render array teaser untuk enam konten dengan tanggal terbaru.
   *
   * Bila tanggal sama, judul menentukan urutan supaya daftar tidak berganti-ganti.
   * Render array (bukan HTML jadi) dikembalikan agar cache tag tiap node ikut naik.
   */
  private function buildLatest() {
    $ids = $this->publishedQuery()
      ->condition('type', array_keys(self::AREAS), 'IN')
      ->condition('field_tanggal', '', '<>')
      ->sort('field_tanggal', 'DESC')
      ->sort('title', 'ASC')
      ->range(0, 6)
      ->execute();

    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($ids);
    $view_builder = $this->entityTypeManager()->getViewBuilder('node');

    $cards = [];
    foreach ($nodes as $node) {
      $cards[] = $view_builder->view($node, 'teaser');
    }
    return $cards;
  }

  /**
   * Chip topik populer: lima term Topik dengan konten terbanyak.
   *
   * shortcut: hitung dari node Published per term, cukup untuk 33 konten seed.
   * Ganti ke View agregasi bila katalog tumbuh besar.
   *
   * @return array
   *   Daftar ['label' => ..., 'url' => ...].
   */
  private function buildTopicChips() {
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $terms = $storage->loadByProperties(['vid' => 'topik']);
    if (empty($terms)) {
      return [];
    }

    $counts = [];
    foreach ($terms as $tid => $term) {
      $count = $this->publishedQuery()
        ->condition('field_topik', $tid)
        ->count()
        ->execute();
      if ($count > 0) {
        $counts[$tid] = $count;
      }
    }
    arsort($counts);

    $chips = [];
    foreach (array_slice($counts, 0, 5, TRUE) as $tid => $count) {
      $label = $terms[$tid]->label();
      $chips[] = [
        'label' => $label,
        'url' => '/cari?search_api_fulltext=' . rawurlencode($label),
      ];
    }
    return $chips;
  }

  /**
   * Pertanyaan populer, dikelompokkan menurut Topik.
   *
   * Sumber: FAQ dan Troubleshooting di area Bantuan.
   *
   * @return array
   *   Daftar grup: ['label', 'anchor', 'questions' => [['title', 'url', 'answer']]].
   */
  private function buildFaq() {
    $ids = $this->publishedQuery()
      ->condition('type', 'bantuan')
      ->sort('title', 'ASC')
      ->range(0, 12)
      ->execute();

    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($ids);

    $groups = [];
    foreach ($nodes as $node) {
      $label = 'Umum';
      if ($node->hasField('field_topik') && !$node->get('field_topik')->isEmpty()) {
        $label = $node->get('field_topik')->entity->label();
      }
      $answer = '';
      if ($node->hasField('field_ringkasan')) {
        $answer = $node->get('field_ringkasan')->value;
      }
      $groups[$label][] = ['title' => $node->label(), 'url' => $node->toUrl()->toString(), 'answer' => $answer];
    }

    $result = [];
    foreach ($groups as $label => $items) {
      $result[] = [
        'label' => $label,
        'anchor' => 'faq-' . Html::cleanCssIdentifier($label),
        'questions' => $items,
      ];
    }
    return $result;
  }

  /**
   * Chip pintasan di bawah pencarian: filter bawaan dan jalan pintas ke area.
   *
   * @return array
   *   Daftar ['label' => ..., 'url' => ...].
   */
  private function buildShortcuts() {
    $chips = [];

    $new_employee = $this->findTermId('audience', 'Pegawai Baru');
    $basic = $this->findTermId('difficulty', 'Dasar');
    if ($new_employee && $basic) {
      $chips[] = [
        'label' => 'Pegawai baru',
        'url' => $this->filteredUrl('/pembelajaran', [['audience', $new_employee], ['difficulty', $basic]]),
      ];
    }

    $troubleshooting = $this->findTermId('jenis_bantuan', 'Troubleshooting');
    if ($troubleshooting) {
      $chips[] = [
        'label' => 'Ada kendala sistem',
        'url' => $this->filteredUrl('/bantuan', [['jenis', $troubleshooting]]),
      ];
    }

    $chips[] = ['label' => 'Prosedur kerja', 'url' => '/prosedur'];
    $chips[] = ['label' => 'Template siap pakai', 'url' => '/alat-kerja'];
    return $chips;
  }

  /**
   * Halaman beranda. Markup ada di templates/kh-home.html.twig.
   */
  public function page() {
    return [
      '#theme' => 'kh_home',
      '#hero' => [
        'search_url' => Url::fromRoute('view.kh_katalog.page_cari')->toString(),
        'chips' => array_merge($this->buildShortcuts(), $this->buildTopicChips()),
      ],
      '#areas' => $this->buildAreas(),
      '#latest' => $this->buildLatest(),
      '#faq' => $this->buildFaq(),
      '#cache' => [
        'tags' => ['node_list', 'taxonomy_term_list'],
        'contexts' => ['user.permissions'],
      ],
    ];
  }

}
