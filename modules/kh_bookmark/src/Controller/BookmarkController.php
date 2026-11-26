<?php

namespace Drupal\kh_bookmark\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Halaman Tersimpan dan penyegar daftar bookmark.
 *
 * Daftar bookmark milik pengunjung ada di localStorage browser (tanpa login).
 * Server hanya dimintai data terbaru untuk id yang dikirim, dan hanya
 * mengembalikan konten yang masih Published dan boleh dilihat.
 */
class BookmarkController extends ControllerBase {

  const BUNDLES = ['prosedur', 'referensi', 'pembelajaran', 'bantuan', 'alat_kerja'];
  const MAX_IDS = 100;

  /**
   * GET /tersimpan.
   */
  public function page() {
    return [
      '#theme' => 'kh_bookmark_page',
      '#cache' => ['max-age' => -1],
    ];
  }

  /**
   * GET /kh/tersimpan-data?ids=1,2,3 mengembalikan {"items": [...]}.
   */
  public function data(Request $request) {
    $raw = (string) $request->query->get('ids', '');
    $ids = [];
    foreach (explode(',', $raw) as $part) {
      $id = (int) $part;
      if ($id > 0) {
        $ids[$id] = $id;
      }
    }
    $ids = array_slice(array_values($ids), 0, self::MAX_IDS);

    $meta = new CacheableMetadata();
    $meta->setCacheMaxAge(60);
    $meta->addCacheContexts(['url.query_args:ids', 'user.node_grants:view', 'user.permissions']);
    $meta->addCacheTags(['node_list']);

    $items = [];
    if ($ids) {
      $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($ids);
      foreach ($ids as $id) {
        $node = $nodes[$id] ?? NULL;
        if (!$node || !in_array($node->bundle(), self::BUNDLES, TRUE) || !$node->isPublished() || !$node->access('view')) {
          continue;
        }
        $meta->addCacheableDependency($node);
        $items[] = [
          'id' => (string) $node->id(),
          'title' => $node->label(),
          'url' => $node->toUrl()->toString(TRUE)->getGeneratedUrl(),
          'jenis' => $this->termLabel($node, 'field_jenis'),
          'snippet' => $this->snippet($node),
        ];
      }
    }

    $response = new CacheableJsonResponse(['items' => $items]);
    $response->addCacheableDependency($meta);
    return $response;
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
    return mb_strlen($text) > 140 ? mb_substr($text, 0, 137) . '…' : $text;
  }

}
