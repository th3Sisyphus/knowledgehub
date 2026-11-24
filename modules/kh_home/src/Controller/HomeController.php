<?php

namespace Drupal\kh_home\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
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

  /**
   * Ikon SVG outline 24x24 untuk kartu area. Dekoratif, jadi aria-hidden.
   */
  const ICONS = [
    'steps' => '<path d="M4 7h16M4 12h10M4 17h13"/>',
    'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M8 7h8M8 11h6"/>',
    'play' => '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 6 3.5-6 3.5z"/>',
    'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.6 2.2c-.7.4-1.1 1-1.1 1.8v.5"/><path d="M12 17.2h.01"/>',
    'doc' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h4"/>',
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
   * Ikon SVG untuk kartu area.
   */
  private function icon($key) {
    $path = self::ICONS[$key] ?? self::ICONS['doc'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
      . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
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
    return $path . ($query ? '?' . http_build_query($query, '', '&amp;') : '');
  }

  /**
   * HTML kartu lima area, lengkap dengan jumlah konten.
   */
  private function buildAreaCards() {
    $html = '';
    foreach (self::AREAS as $bundle => $area) {
      $count = $this->publishedQuery()
        ->condition('type', $bundle)
        ->count()
        ->execute();

      $html .= '<article class="area-card">';
      $html .= '<span class="area-icon">' . $this->icon($area['icon']) . '</span>';
      $html .= '<h3><a href="' . $area['path'] . '">' . Html::escape($area['label']) . '</a></h3>';
      $html .= '<p>' . Html::escape($area['description']) . '</p>';
      $html .= '<div class="area-foot"><span>' . $count . ' konten</span>';
      $html .= '<span class="goto" aria-hidden="true">Buka &rarr;</span></div>';
      $html .= '</article>';
    }
    return $html;
  }

  /**
   * HTML kartu konten terbaru: enam konten dengan tanggal terbaru.
   *
   * Bila tanggal sama, judul menentukan urutan supaya daftar tidak berganti-ganti.
   */
  private function buildLatestCards() {
    $ids = $this->publishedQuery()
      ->condition('type', array_keys(self::AREAS), 'IN')
      ->condition('field_tanggal', '', '<>')
      ->sort('field_tanggal', 'DESC')
      ->sort('title', 'ASC')
      ->range(0, 6)
      ->execute();

    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($ids);
    $view_builder = $this->entityTypeManager()->getViewBuilder('node');

    $html = '';
    foreach ($nodes as $node) {
      $build = $view_builder->view($node, 'teaser');
      $html .= \Drupal::service('renderer')->renderPlain($build);
    }
    return $html;
  }

  /**
   * Chip topik populer: lima term Topik dengan konten terbanyak.
   *
   * shortcut: hitung dari node Published per term, cukup untuk 33 konten seed.
   * Ganti ke View agregasi bila katalog tumbuh besar.
   */
  private function buildTopicChips() {
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $terms = $storage->loadByProperties(['vid' => 'topik']);
    if (empty($terms)) {
      return '';
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

    $html = '';
    foreach (array_slice($counts, 0, 5, TRUE) as $tid => $count) {
      $html .= '<li><a class="chip" href="/cari?' . Html::escape('search_api_fulltext=' . rawurlencode($terms[$tid]->label())) . '">'
        . Html::escape($terms[$tid]->label()) . '</a></li>';
    }
    return $html;
  }

  /**
   * Pertanyaan populer: chip kategori di kiri dan akordeon <details> di kanan.
   *
   * Sumber: FAQ dan Troubleshooting di area Bantuan, dikelompokkan menurut Topik.
   */
  private function buildFaq() {
    $ids = $this->publishedQuery()
      ->condition('type', 'bantuan')
      ->sort('title', 'ASC')
      ->range(0, 12)
      ->execute();

    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($ids);
    if (empty($nodes)) {
      return '';
    }

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

    $side = '';
    $panels = '';
    $first = TRUE;
    foreach ($groups as $label => $items) {
      $anchor = Html::cleanCssIdentifier($label);
      $side .= '<a href="#faq-' . $anchor . '"' . ($first ? ' aria-current="true"' : '') . '>'
        . Html::escape($label) . '</a>';

      $panels .= '<div class="accordion" id="faq-' . $anchor . '">';
      foreach ($items as $item) {
        $panels .= '<details>';
        $panels .= '<summary>' . Html::escape($item['title']) . '</summary>';
        $panels .= '<p>' . Html::escape($item['answer']);
        $panels .= ' <a href="' . $item['url'] . '">Buka jawaban lengkap</a></p>';
        $panels .= '</details>';
      }
      $panels .= '</div>';
      $first = FALSE;
    }

    return '<div class="faq-layout"><div class="faq-side">' . $side . '</div><div>' . $panels . '</div></div>';
  }

  /**
   * Chip di bawah pencarian: pintasan ke filter bawaan.
   */
  private function buildShortcuts() {
    $html = '';

    $new_employee = $this->findTermId('audience', 'Pegawai Baru');
    $basic = $this->findTermId('difficulty', 'Dasar');
    if ($new_employee && $basic) {
      $html .= '<li><a class="chip" href="'
        . $this->filteredUrl('/pembelajaran', [['audience', $new_employee], ['difficulty', $basic]])
        . '">Pegawai baru</a></li>';
    }

    $troubleshooting = $this->findTermId('jenis_bantuan', 'Troubleshooting');
    if ($troubleshooting) {
      $html .= '<li><a class="chip" href="'
        . $this->filteredUrl('/bantuan', [['jenis', $troubleshooting]])
        . '">Ada kendala sistem</a></li>';
    }

    $html .= '<li><a class="chip" href="/prosedur">Prosedur kerja</a></li>';
    $html .= '<li><a class="chip" href="/alat-kerja">Template siap pakai</a></li>';
    return $html;
  }

  /**
   * HTML hero dengan pencarian besar dan chip topik populer.
   */
  private function buildHero() {
    $search_url = Url::fromRoute('view.kh_katalog.page_cari')->toString();

    $html = '<section class="hero blob-field">';
    $html .= '<h1>Temukan jawaban untuk<span>setiap pertanyaan kerja</span></h1>';
    $html .= '<p>SOP, kebijakan, tutorial, FAQ, dan template untuk satu proses ada di satu tempat.</p>';
    $html .= '<form class="hero-search" action="' . $search_url . '" method="get" role="search">';
    $html .= '<label for="q-hero" class="visually-hidden">Cari di Knowledge Hub</label>';
    $html .= '<input id="q-hero" name="search_api_fulltext" type="search" ';
    $html .= 'placeholder="Contoh: bagaimana mengajukan perjalanan dinas?">';
    $html .= '<button class="button button--primary" type="submit">Cari</button>';
    $html .= '</form>';
    $html .= '<p class="chip-label">Topik populer</p>';
    $html .= '<ul class="chips">' . $this->buildShortcuts() . $this->buildTopicChips() . '</ul>';
    $html .= '</section>';
    return $html;
  }

  /**
   * Halaman beranda.
   */
  public function page() {
    $html = '<div class="wrap">';
    $html .= $this->buildHero();

    $html .= '<section class="section">';
    $html .= '<div class="section-head"><div><h2>Jelajahi lima area</h2>';
    $html .= '<p>Mulai dari jenis bantuan yang paling dekat dengan kebutuhan Anda.</p></div></div>';
    $html .= '<div class="area-grid">' . $this->buildAreaCards() . '</div>';
    $html .= '</section>';

    $html .= '<section class="section">';
    $html .= '<div class="section-head"><div><h2>Baru diperbarui</h2>';
    $html .= '<p>Dokumen dengan pembaruan terakhir.</p></div>';
    $html .= '<a class="section-link" href="/cari">Lihat semua</a></div>';
    $html .= '<div class="teaser-grid">' . $this->buildLatestCards() . '</div>';
    $html .= '</section>';

    $faq = $this->buildFaq();
    if ($faq) {
      $html .= '<section class="section">';
      $html .= '<div class="section-head"><div><h2>Pertanyaan populer</h2>';
      $html .= '<p>Jawaban singkat untuk kendala yang paling sering muncul.</p></div></div>';
      $html .= $faq;
      $html .= '</section>';
    }

    $html .= '<section class="section"><div class="cta">';
    $html .= '<h2>Butuh jawaban yang belum ada di sini?</h2>';
    $html .= '<p>Hubungi Helpdesk IT atau kirim pertanyaan ke Knowledge Manager. Kami bantu arahkan ke dokumen yang tepat.</p>';
    $html .= '<div class="cta-actions">';
    $html .= '<a class="button button--primary" href="/bantuan">Lihat Bantuan</a>';
    $html .= '<a class="button" href="/cari">Telusuri semua konten</a>';
    $html .= '</div></div></section>';

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
