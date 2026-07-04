<?php
/**
 * Tab File: contenuto del primo load. Pagine successive via sentinel
 * infinite-scroll dentro files_list_page.php.
 *
 * Le pills filtro (kind) emettono hx-get verso teams.panel.files con
 * hx-target sul wrapper interno; al cambio di filtro la lista viene
 * ricaricata da capo.
 *
 * @var int    $conversationId
 * @var array  $items
 * @var bool   $hasMore
 * @var ?int   $nextBefore
 * @var string $kind
 */
?>
<?php $view->include('Teams/Views/partials/group_panel/files_list_page', [
    'conversationId' => $conversationId,
    'items'          => $items,
    'hasMore'        => $hasMore,
    'nextBefore'     => $nextBefore,
    'kind'           => $kind,
    'isFirstPage'    => true,
]); ?>
