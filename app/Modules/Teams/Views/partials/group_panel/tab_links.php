<?php
/**
 * Tab Link: contenuto del primo load. Pagine successive via sentinel
 * infinite-scroll dentro links_list_page.php.
 *
 * @var int   $conversationId
 * @var array $items
 * @var bool  $hasMore
 * @var ?int  $nextBefore
 */
?>
<?php $view->include('Teams/Views/partials/group_panel/links_list_page', [
    'conversationId' => $conversationId,
    'items'          => $items,
    'hasMore'        => $hasMore,
    'nextBefore'     => $nextBefore,
    'isFirstPage'    => true,
]); ?>
