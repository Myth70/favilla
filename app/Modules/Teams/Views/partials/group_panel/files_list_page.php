<?php
/**
 * Pagina di file (non-image/non-video) con filtri pills (Tutti/Documenti/Archivi/Audio).
 *
 * Prima pagina: render pills + wrapper #tm-gp-files-list. Successive: solo righe + nuovo sentinel.
 *
 * @var int    $conversationId
 * @var array  $items
 * @var bool   $hasMore
 * @var ?int   $nextBefore
 * @var string $kind           docs|archives|audio|all
 * @var bool   $isFirstPage
 */
use App\Modules\Teams\Support\TeamsFileIcon;
use App\Modules\Teams\Support\TeamsFileSize;

$baseUrl = route('teams.panel.files', ['id' => $conversationId]);
$kinds = [
    'all'      => t('teams.file_kind.all'),
    'docs'     => t('teams.file_kind.docs'),
    'archives' => t('teams.file_kind.archives'),
    'audio'    => t('teams.file_kind.audio'),
];

/** Render di una singola riga file. */
$renderRow = function (array $a): void {
    $mime = (string) ($a['mime_type'] ?? '');
    $ext  = (string) ($a['extension'] ?? '');
    $name = (string) ($a['original_name'] ?? t('teams.group_panel.file_fallback_name'));
    $url  = !empty($a['id']) ? route('teams.attachments.show', ['attachmentId' => (int) $a['id']]) : '#';
    $size = (int) ($a['size_bytes'] ?? 0);
    $when = isset($a['msg_created_at']) ? format_date((string) $a['msg_created_at'], 'relative') : '';
    $by   = (string) ($a['user_name'] ?? '');
    $icon = TeamsFileIcon::iconClass($mime, $ext);
    ?>
    <a class="tm-gp-file-row"
       href="<?= e($url) ?>"
       target="_blank"
       rel="noopener"
       title="<?= e($name) ?>">
        <span class="tm-gp-file-icon" aria-hidden="true">
            <i class="fa-solid <?= e($icon) ?>"></i>
        </span>
        <span class="tm-gp-file-info">
            <span class="tm-gp-file-name"><?= e($name) ?></span>
            <span class="tm-gp-file-meta">
                <?= e(TeamsFileSize::format($size)) ?>
                <?php if ($by !== ''): ?>
                    <span class="tm-gp-meta-sep">·</span><?= e($by) ?>
                <?php endif; ?>
                <?php if ($when !== ''): ?>
                    <span class="tm-gp-meta-sep">·</span><?= e($when) ?>
                <?php endif; ?>
            </span>
        </span>
        <span class="tm-gp-file-download" aria-hidden="true">
            <i class="fa-solid fa-download"></i>
        </span>
    </a>
    <?php
};
?>
<?php if (!empty($isFirstPage)): ?>
    <div class="tm-gp-tab-header">
        <div class="tm-gp-pills">
            <?php foreach ($kinds as $k => $label): ?>
                <button type="button"
                        class="tm-gp-pill<?= $k === $kind ? ' active' : '' ?>"
                        hx-get="<?= e($baseUrl) ?>?kind=<?= e($k) ?>"
                        hx-target="#tm-gp-tab-files"
                        hx-swap="innerHTML"
                        aria-pressed="<?= $k === $kind ? 'true' : 'false' ?>">
                    <?= e($label) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if (empty($items)): ?>
        <div class="tm-gp-empty">
            <i class="fa-regular fa-file"></i>
            <p><?= $kind === 'all' ? e(t('teams.group_panel.no_files_all')) : e(t('teams.group_panel.no_files_kind')) ?></p>
        </div>
    <?php else: ?>
        <div id="tm-gp-files-list" class="tm-gp-files-list">
            <?php foreach ($items as $a) {
                $renderRow($a);
            } ?>
            <?php if (!empty($hasMore) && !empty($nextBefore)): ?>
                <div id="tm-gp-files-sentinel"
                     class="tm-gp-infinite-sentinel"
                     hx-get="<?= e($baseUrl) ?>?kind=<?= e($kind) ?>&before_id=<?= (int) $nextBefore ?>"
                     hx-trigger="revealed"
                     hx-target="#tm-gp-files-sentinel"
                     hx-swap="outerHTML">
                    <span class="spinner-border spinner-border-sm"></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php foreach ($items as $a) {
        $renderRow($a);
    } ?>
    <?php if (!empty($hasMore) && !empty($nextBefore)): ?>
        <div id="tm-gp-files-sentinel"
             class="tm-gp-infinite-sentinel"
             hx-get="<?= e($baseUrl) ?>?kind=<?= e($kind) ?>&before_id=<?= (int) $nextBefore ?>"
             hx-trigger="revealed"
             hx-target="#tm-gp-files-sentinel"
             hx-swap="outerHTML">
            <span class="spinner-border spinner-border-sm"></span>
        </div>
    <?php endif; ?>
<?php endif; ?>
