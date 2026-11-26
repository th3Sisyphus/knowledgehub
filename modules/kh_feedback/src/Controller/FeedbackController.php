<?php

namespace Drupal\kh_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Flood\FloodInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Menerima dan melaporkan umpan balik "Apakah halaman ini membantu?".
 */
class FeedbackController extends ControllerBase {

  /**
   * Hanya lima content type Knowledge Hub yang menerima umpan balik.
   */
  const BUNDLES = ['prosedur', 'referensi', 'pembelajaran', 'bantuan', 'alat_kerja'];

  /**
   * Batas kiriman per perangkat per konten: 5 kali per jam.
   */
  const FLOOD_LIMIT = 5;
  const FLOOD_WINDOW = 3600;

  protected $database;
  protected $flood;

  public function __construct(Connection $database, FloodInterface $flood) {
    $this->database = $database;
    $this->flood = $flood;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('flood')
    );
  }

  /**
   * Simpan satu umpan balik. Isi: {"helpful": 1|0, "comment": "..."}.
   */
  public function submit(NodeInterface $node, Request $request) {
    if (!in_array($node->bundle(), self::BUNDLES, TRUE) || !$node->isPublished() || !$node->access('view')) {
      return new JsonResponse(['ok' => FALSE], 403);
    }

    $flood_id = $request->getClientIp() . ':' . $node->id();
    if (!$this->flood->isAllowed('kh_feedback.submit', self::FLOOD_LIMIT, self::FLOOD_WINDOW, $flood_id)) {
      return new JsonResponse(['ok' => FALSE], 429);
    }

    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data) || !array_key_exists('helpful', $data)) {
      return new JsonResponse(['ok' => FALSE], 400);
    }

    $comment = trim(strip_tags((string) ($data['comment'] ?? '')));
    $comment = mb_substr($comment, 0, 500);

    $this->database->insert('kh_feedback')
      ->fields([
        'nid' => $node->id(),
        'helpful' => empty($data['helpful']) ? 0 : 1,
        'comment' => $comment,
        'uid' => $this->currentUser()->id(),
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
    $this->flood->register('kh_feedback.submit', self::FLOOD_WINDOW, $flood_id);

    return new JsonResponse(['ok' => TRUE]);
  }

  /**
   * Laporan untuk Knowledge Manager: ringkasan per konten dan komentar terbaru.
   */
  public function report() {
    $query = $this->database->select('kh_feedback', 'f');
    $query->addField('f', 'nid');
    $query->addExpression('SUM(f.helpful)', 'up');
    $query->addExpression('COUNT(f.id) - SUM(f.helpful)', 'down');
    $query->groupBy('f.nid');
    $query->orderBy('down', 'DESC');
    $query->range(0, 50);
    $summary = $query->execute()->fetchAll();

    $nodes = $this->entityTypeManager()->getStorage('node')
      ->loadMultiple(array_map(function ($row) { return $row->nid; }, $summary));

    $rows = [];
    foreach ($summary as $row) {
      $node = $nodes[$row->nid] ?? NULL;
      if (!$node) {
        continue;
      }
      $rows[] = [
        ['data' => $node->toLink()->toRenderable()],
        (int) $row->up,
        (int) $row->down,
      ];
    }

    $comments = $this->database->select('kh_feedback', 'f')
      ->fields('f', ['nid', 'comment', 'created'])
      ->condition('f.helpful', 0)
      ->condition('f.comment', '', '<>')
      ->orderBy('f.created', 'DESC')
      ->range(0, 20)
      ->execute()->fetchAll();

    $comment_rows = [];
    foreach ($comments as $c) {
      $node = $this->entityTypeManager()->getStorage('node')->load($c->nid);
      $comment_rows[] = [
        $node ? $node->label() : '#' . $c->nid,
        $c->comment,
        \Drupal::service('date.formatter')->format($c->created, 'short'),
      ];
    }

    return [
      'summary' => [
        '#type' => 'table',
        '#caption' => $this->t('Konten dengan masukan "Belum membantu" terbanyak'),
        '#header' => [$this->t('Konten'), '👍', '👎'],
        '#rows' => $rows,
        '#empty' => $this->t('Belum ada umpan balik.'),
      ],
      'comments' => [
        '#type' => 'table',
        '#caption' => $this->t('Komentar terbaru dari "Belum membantu"'),
        '#header' => [$this->t('Konten'), $this->t('Komentar'), $this->t('Waktu')],
        '#rows' => $comment_rows,
        '#empty' => $this->t('Belum ada komentar.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
