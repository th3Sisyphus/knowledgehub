<?php

namespace Drupal\kh_search\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\Request;

/**
 * Hasil cepat untuk command palette.
 *
 * Index Search API memakai matching "words", jadi kata yang belum selesai diketik
 * ("prose") tidak cocok. Karena itu hasil digabung dari dua sumber: judul yang
 * memuat kata (cocok saat mengetik) dan teks lengkap Search API (cocok ke isi).
 */
class QuickSearchController extends ControllerBase {

  const BUNDLES = ['prosedur', 'referensi', 'pembelajaran', 'bantuan', 'alat_kerja'];
  const MAX_ITEMS = 8;
  const INDEX_ID = 'kh_index';

  /**
   * GET /kh/cari-cepat?q=... mengembalikan {"items": [...]}.
   */
  public function results(Request $request) {
    $q = trim(preg_replace('/\s+/u', ' ', (string) $request->query->get('q', '')));
    $q = mb_substr($q, 0, 80);

    $meta = new CacheableMetadata();
    $meta->setCacheMaxAge(60);
    $meta->addCacheContexts(['url.query_args:q', 'user.node_grants:view', 'user.permissions']);
    $meta->addCacheTags(['node_list', 'search_api_list:' . self::INDEX_ID]);

    $items = [];
    if (mb_strlen($q) >= 2) {
      $title_hits = $this->titleHits($q);
      $text_hits = $this->textHits($q);

      $storage = $this->entityTypeManager()->getStorage('node');
      $seen = [];
      $ordered = [];
      foreach ([['judul', $title_hits], ['isi', $text_hits]] as [$match, $nids]) {
        foreach ($nids as $nid) {
          if (!isset($seen[$nid])) {
            $seen[$nid] = TRUE;
            $ordered[$nid] = $match;
          }
        }
      }
      $nodes = $storage->loadMultiple(array_slice(array_keys($ordered), 0, self::MAX_ITEMS));

      foreach ($ordered as $nid => $match) {
        if (count($items) >= self::MAX_ITEMS) {
          break;
        }
        $node = $nodes[$nid] ?? NULL;
        if (!$node || !$node->isPublished() || !$node->access('view')) {
          continue;
        }
        $meta->addCacheableDependency($node);
        $items[] = [
          'title' => $node->label(),
          'url' => $node->toUrl()->toString(TRUE)->getGeneratedUrl(),
          'jenis' => $this->termLabel($node, 'field_jenis'),
          'snippet' => $this->snippet($node),
          'match' => $match,
        ];
      }
    }

    $response = new CacheableJsonResponse(['items' => $items]);
    $response->addCacheableDependency($meta);
    return $response;
  }

  /**
   * Node yang judulnya memuat semua kata. Yang berawalan kata pertama didahulukan.
   */
  protected function titleHits($q) {
    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', self::BUNDLES, 'IN');
    foreach (array_slice(explode(' ', $q), 0, 5) as $word) {
      if ($word !== '') {
        $query->condition('title', $word, 'CONTAINS');
      }
    }
    $nids = array_values($query->sort('changed', 'DESC')->range(0, 20)->execute());
    if (!$nids) {
      return [];
    }

    $nodes = $storage->loadMultiple($nids);
    $needle = mb_strtolower($q);
    usort($nids, function ($a, $b) use ($nodes, $needle) {
      $a_start = mb_strpos(mb_strtolower($nodes[$a]->label()), $needle) === 0 ? 0 : 1;
      $b_start = mb_strpos(mb_strtolower($nodes[$b]->label()), $needle) === 0 ? 0 : 1;
      return $a_start <=> $b_start;
    });
    return array_slice($nids, 0, self::MAX_ITEMS);
  }

  /**
   * Hasil teks lengkap dari index Search API. Gagal diam-diam bila index belum siap.
   */
  protected function textHits($q) {
    $nids = [];
    try {
      $index = Index::load(self::INDEX_ID);
      if (!$index || !$index->status()) {
        return [];
      }
      $query = $index->query();
      $query->setParseMode(\Drupal::service('plugin.manager.search_api.parse_mode')->createInstance('terms'));
      $query->keys($q);
      $query->setFulltextFields(['title', 'field_ringkasan', 'body']);
      $query->sort('search_api_relevance', 'DESC');
      $query->range(0, self::MAX_ITEMS);
      foreach ($query->execute()->getResultItems() as $item) {
        if (preg_match('/^entity:node\/(\d+):/', $item->getId(), $m)) {
          $nids[] = (int) $m[1];
        }
      }
    }
    catch (\Exception $e) {
      $this->getLogger('kh_search')->warning('Pencarian teks lengkap gagal: @msg', ['@msg' => $e->getMessage()]);
    }
    return $nids;
  }

  protected function termLabel($node, $field) {
    if ($node->hasField($field) && !$node->get($field)->isEmpty() && $node->get($field)->entity) {
      return $node->get($field)->entity->label();
    }
    return '';
  }

  protected function snippet($node) {
    if (!$node->hasField('field_ringkasan') || $node->get('field_ringkasan')->isEmpty()) {
      return '';
    }
    $text = trim(strip_tags($node->get('field_ringkasan')->value));
    return mb_strlen($text) > 110 ? mb_substr($text, 0, 107) . '…' : $text;
  }

}
