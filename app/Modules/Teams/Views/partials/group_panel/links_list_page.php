<?php
/**
 * Pagina di link estratti dai body dei messaggi.
 *
 * Prima pagina: render wrapper #tm-gp-links-list. Successive: solo righe + nuovo sentinel.
 *
 * Le righe usano favicon di Google s2 (no fetch dal nostro server, no privacy
 * concerns oltre quelle del classico embed). Fallback icona `fa-link` se
 * l'host è vuoto/malformato.
 *
 * @var int   $conversationId
 * @var array $items          flatten [{url, domain, message_id, body, user_id, user_name, created_at}, ...]
 * @var bool  $hasMore
 * @var ?int  $nextBefore
 * @var bool  $isFirstPage
 */

$baseUrl = route('teams.panel.links', ['id' => $conversationId]);

/** Render di una singola riga link. */
$renderRow = function (array $row): void {
    $url    = (string) ($row['url'] ?? '');
    $domain = (string) ($row['domain'] ?? '');
    $by     = (string) ($row['user_name'] ?? '');
    $when   = isset($row['created_at']) ? format_date((string) $row['created_at'], 'relative') : '';
    $favicon = $domain !== ''
        ? 'https://www.google.com/s2/favicons?domain=' . rawurlencode($domain) . '&sz=32'
        : '';
    ?>
    <a class="tm-gp-link-row"
       href="<?= e($url) ?>"
       target="_blank"
       rel="noopener nofollow"
       title="<?= e($url) ?>">
        <span class="tm-gp-link-icon" aria-hidden="true">
            <?php if ($favicon !== ''): ?>
                <img src="<?= e($favicon) ?>" alt="" width="20" height="20"
                     onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'fa-solid fa-link'}))">
            <?php else: ?>
                <i class="fa-solid fa-link"></i>
            <?php endif; ?>
        </span>
        <span class="tm-gp-link-info">
            <span class="tm-gp-link-domain"><?= e($domain !== '' ? $domain : t('teams.group_panel.link_fallback_domain')) ?></span>
            <span class="tm-gp-link-url"><?= e($url) ?></span>
            <?php if ($by !== '' || $when !== ''): ?>
            <span class="tm-gp-link-meta">
                <?php if ($by !== ''): ?><?= e(t('teams.group_panel.link_by', ['name' => $by])) ?><?php endif; ?>
                <?php if ($by !== '' && $when !== ''): ?><span class="tm-gp-meta-sep">·</span><?php endif; ?>
                <?php if ($when !== ''): ?><?= e($when) ?><?php endif; ?>
            </span>
            <?php endif; ?>
        </span>
        <span class="tm-gp-link-arrow" aria-hidden="true">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
        </span>
    </a>
    <?php
};
?>
<?php if (!empty($isFirstPage)): ?>
    <?php if (empty($items) && empty($hasMore)): ?>
        <div class="tm-gp-empty">
            <i class="fa-solid fa-link-slash"></i>
            <p><?= e(t('teams.group_panel.no_links')) ?></p>
        </div>
    <?php else: ?>
        <?php /* Anche con items=[] dobbiamo emettere il sentinel se hasMore:
                 il pre-filter LIKE può matchare messaggi che poi falliscono la
                 regex URL (es. "info@www.foo" non è un link valido), quindi la
                 prima pagina può essere vuota mentre indietro ci sono link veri. */ ?>
        <div id="tm-gp-links-list" class="tm-gp-links-list">
            <?php foreach ($items as $row) {
                $renderRow($row);
            } ?>
            <?php if (!empty($hasMore) && !empty($nextBefore)): ?>
                <div id="tm-gp-links-sentinel"
                     class="tm-gp-infinite-sentinel"
                     hx-get="<?= e($baseUrl) ?>?before_id=<?= (int) $nextBefore ?>"
                     hx-trigger="revealed"
                     hx-target="#tm-gp-links-sentinel"
                     hx-swap="outerHTML">
                    <span class="spinner-border spinner-border-sm"></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php foreach ($items as $row) {
        $renderRow($row);
    } ?>
    <?php if (!empty($hasMore) && !empty($nextBefore)): ?>
        <div id="tm-gp-links-sentinel"
             class="tm-gp-infinite-sentinel"
             hx-get="<?= e($baseUrl) ?>?before_id=<?= (int) $nextBefore ?>"
             hx-trigger="revealed"
             hx-target="#tm-gp-links-sentinel"
             hx-swap="outerHTML">
            <span class="spinner-border spinner-border-sm"></span>
        </div>
    <?php endif; ?>
<?php endif; ?>
