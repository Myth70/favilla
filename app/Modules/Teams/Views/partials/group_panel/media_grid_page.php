<?php
/**
 * Pagina di media (immagini + video) — celle thumbnail.
 *
 * Sulla prima pagina renderizza anche il wrapper (#tm-gp-media-grid) e
 * l'header con counter. Sulle pagine successive emette SOLO le celle +
 * il nuovo sentinel (HTMX swap outerHTML del sentinel precedente).
 *
 * @var int   $conversationId
 * @var array $items          righe di teams_message_attachments + dati messaggio
 * @var bool  $hasMore
 * @var ?int  $nextBefore
 * @var bool  $isFirstPage
 * @var int   $total          totale media (solo prima pagina)
 */

/** Render di una singola cella media. */
$renderCell = function (array $a): void {
    $mime = strtolower((string) ($a['mime_type'] ?? ''));
    $url  = !empty($a['id']) ? route('teams.attachments.show', ['attachmentId' => (int) $a['id']]) : '';
    $name = (string) ($a['original_name'] ?? t('teams.group_panel.media_fallback_name'));
    $when = isset($a['msg_created_at']) ? format_date((string) $a['msg_created_at'], 'relative') : '';
    $by   = (string) ($a['user_name'] ?? '');
    $caption = trim(($by !== '' ? $by : '') . ($when !== '' ? ' · ' . $when : ''));
    $isVideo = str_starts_with($mime, 'video/');
    ?>
    <div class="tm-gp-media-cell">
        <?php if ($isVideo): ?>
            <?php /* Video: niente classe lightbox, click apre il file in nuova
                     tab dove il browser usa il player nativo. La thumbnail mostra
                     il primo frame via <video preload="metadata">. */ ?>
            <a class="tm-gp-media-video-link" href="<?= e($url) ?>" target="_blank" rel="noopener"
               title="<?= e($name) ?>" aria-label="<?= e(t('teams.group_panel.open_video_aria', ['name' => $name])) ?>">
                <video class="tm-gp-media-thumb"
                       preload="metadata"
                       muted playsinline>
                    <source src="<?= e($url) ?>" type="<?= e($mime) ?>">
                </video>
                <span class="tm-gp-media-play" aria-hidden="true">
                    <i class="fa-solid fa-play"></i>
                </span>
            </a>
        <?php else: ?>
            <img src="<?= e($url) ?>"
                 data-fullsrc="<?= e($url) ?>"
                 data-caption="<?= e($name . ($caption !== '' ? ' — ' . $caption : '')) ?>"
                 alt="<?= e($name) ?>"
                 class="tm-gp-media-thumb tm-msg-image"
                 loading="lazy">
        <?php endif; ?>
    </div>
    <?php
};
?>
<?php if (!empty($isFirstPage)): ?>
    <?php if (empty($items)): ?>
        <div class="tm-gp-empty">
            <i class="fa-regular fa-image"></i>
            <p><?= e(t('teams.group_panel.no_media')) ?></p>
        </div>
    <?php else: ?>
        <div class="tm-gp-tab-header">
            <span class="tm-gp-tab-count"><?= e(tc('teams.group_panel.media_count', (int) $total)) ?></span>
        </div>
        <div id="tm-gp-media-grid" class="tm-gp-media-grid">
            <?php foreach ($items as $a) {
                $renderCell($a);
            } ?>
            <?php if (!empty($hasMore) && !empty($nextBefore)): ?>
                <div id="tm-gp-media-sentinel"
                     class="tm-gp-infinite-sentinel"
                     hx-get="<?= e(route('teams.panel.media', ['id' => $conversationId])) ?>?before_id=<?= (int) $nextBefore ?>"
                     hx-trigger="revealed"
                     hx-target="#tm-gp-media-sentinel"
                     hx-swap="outerHTML">
                    <span class="spinner-border spinner-border-sm"></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php foreach ($items as $a) {
        $renderCell($a);
    } ?>
    <?php if (!empty($hasMore) && !empty($nextBefore)): ?>
        <div id="tm-gp-media-sentinel"
             class="tm-gp-infinite-sentinel"
             hx-get="<?= e(route('teams.panel.media', ['id' => $conversationId])) ?>?before_id=<?= (int) $nextBefore ?>"
             hx-trigger="revealed"
             hx-target="#tm-gp-media-sentinel"
             hx-swap="outerHTML">
            <span class="spinner-border spinner-border-sm"></span>
        </div>
    <?php endif; ?>
<?php endif; ?>
