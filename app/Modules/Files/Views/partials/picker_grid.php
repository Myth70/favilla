<?php
/**
 * File Picker — griglia risultati (HTMX partial).
 * Variabili: $items, $total, $page, $totalPages, $q, $mimeFilter
 */
?>
<?php if (empty($items)): ?>
<div class="text-center text-muted py-5">
    <i class="fa-solid fa-folder-open fa-2x mb-2 d-block"></i>
    <?php if ($q !== ''): ?>
        <?= t('files.picker.no_results', ['query' => e($q)]) ?>
    <?php else: ?>
        <?= e(t('files.picker.empty')) ?>
    <?php endif; ?>
</div>
<?php else: ?>

<div class="row g-2 mb-2">
<?php foreach ($items as $item):
    $isImage  = str_starts_with($item['mime_type'], 'image/');
    $fullUrl  = route('files.preview', ['id' => (int) $item['id']]);
    $fpValue  = $item['directory'] . '/' . $item['stored_name'];
    $fpLabel  = $item['original_name'];
    $iconClass = match(true) {
        str_starts_with($item['mime_type'], 'image/')       => 'fa-file-image text-success',
        $item['mime_type'] === 'application/pdf'            => 'fa-file-pdf text-danger',
        str_starts_with($item['mime_type'], 'text/')        => 'fa-file-lines text-info',
        str_contains($item['mime_type'], 'spreadsheet') || str_contains($item['mime_type'], 'excel')
                                                            => 'fa-file-excel text-success',
        str_contains($item['mime_type'], 'word') || str_contains($item['mime_type'], 'document')
                                                            => 'fa-file-word text-primary',
        str_contains($item['mime_type'], 'zip') || str_contains($item['mime_type'], 'rar')
                                                            => 'fa-file-zipper text-warning',
        default => 'fa-file text-secondary',
    };
?>
    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
        <div class="card h-100 fp-item fm-picker-card"
             role="button" tabindex="0"
             data-fp-pick="1"
             data-fp-value="<?= e($fpValue) ?>"
             data-fp-label="<?= e($fpLabel) ?>"
             data-fp-url="<?= e($fullUrl) ?>"
             title="<?= e($fpLabel) ?>">
            <div class="d-flex align-items-center justify-content-center fm-picker-thumb">
                <?php if ($isImage): ?>
                    <img src="<?= e($fullUrl) ?>" alt="<?= e($fpLabel) ?>"
                         class="fm-picker-thumb-img">
                <?php else: ?>
                    <i class="fa-solid <?= $iconClass ?> fa-2x"></i>
                <?php endif; ?>
            </div>
            <div class="card-body p-1">
                <div class="text-truncate fm-picker-label"
                     title="<?= e($fpLabel) ?>">
                    <?= e($fpLabel) ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<nav class="d-flex justify-content-center mt-1">
    <ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link"
               hx-get="<?= e(route('files.picker')) ?>?page=<?= $p ?>&q=<?= urlencode($q) ?>&mime=<?= urlencode($mimeFilter) ?>"
               hx-target="#fp-results"
               hx-swap="innerHTML"
               href="#"><?= $p ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>
