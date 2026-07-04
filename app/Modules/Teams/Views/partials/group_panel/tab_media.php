<?php
/**
 * Tab Media: contenuto del primo load. Il page-by-page successivo è
 * gestito dal sentinel infinite-scroll dentro media_grid_page.php.
 *
 * In genere questo file NON viene incluso direttamente: il tab è lazy
 * (vedi tabs.php), il controller risponde direttamente con media_grid_page.
 * Resta utile come placeholder strutturale e per eventuali test/uso futuro.
 *
 * @var int   $conversationId
 * @var array $items
 * @var bool  $hasMore
 * @var ?int  $nextBefore
 * @var int   $total
 */
?>
<?php $view->include('Teams/Views/partials/group_panel/media_grid_page', [
    'conversationId' => $conversationId,
    'items'          => $items,
    'hasMore'        => $hasMore,
    'nextBefore'     => $nextBefore,
    'total'          => $total,
    'isFirstPage'    => true,
]); ?>
