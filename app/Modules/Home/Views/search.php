<?php $view->layout('main'); ?>
<?php $view->start('content'); ?>
<?php
/** Highlight search term in pre-escaped text. */
function search_highlight(string $text, string $query): string
{
    $escaped = e($text);
    if ($query === '') {
        return $escaped;
    }
    return preg_replace(
        '/(' . preg_quote($query, '/') . ')/i',
        '<mark>$1</mark>',
        $escaped
    );
}

$searchButtons = '';
if (($q ?? '') !== '') {
    $searchButtons = '<a href="' . e(route('search.index')) . '" class="btn btn-sm btn-outline-secondary">'
        . '<i class="fa-solid fa-rotate-left me-1"></i>' . e(t('home.search.reset')) . '</a>';
}

$searchSubtitle = ($q ?? '') !== ''
    ? t('home.search.subtitle_results', ['count' => (int) ($totalResults ?? 0), 'query' => e((string) $q)])
    : t('home.search.subtitle');
?>

<div class="container-fluid">
<?php $view->include('partials/pf-hero-module', [
    'moduleName'     => t('home.search.title'),
    'moduleIcon'     => 'fa-solid fa-magnifying-glass',
    'moduleSubtitle' => $searchSubtitle,
    'moduleButtons'  => $searchButtons,
]); ?>
<div class="card">
    <div class="card-body">
        <form method="GET" action="<?= e(route('search.index')) ?>" class="mb-4">
            <div class="input-group input-group-lg">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="q" class="form-control" placeholder="<?= e(t('home.search.placeholder')) ?>"
                       value="<?= e($q ?? '') ?>" autofocus>
                <button class="btn btn-primary" type="submit"><?= e(t('home.search.submit')) ?></button>
            </div>
        </form>

        <?php if ($q !== ''): ?>
            <p class="text-muted mb-4">
                <?= tc('home.search.results_count', (int) $totalResults, ['query' => e($q)]) ?>
            </p>

            <?php if (empty($grouped)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-magnifying-glass fa-3x mb-3 opacity-25"></i>
                    <p class="mb-0"><?= e(t('home.search.no_results')) ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($grouped as $group): ?>
                    <div class="mb-4">
                        <h5 class="d-flex align-items-center gap-2 mb-3 border-bottom pb-2">
                            <i class="fa-solid <?= e($group['icon']) ?> text-muted"></i>
                            <?= e($group['label']) ?>
                            <span class="badge bg-secondary rounded-pill"><?= count($group['results']) ?></span>
                        </h5>
                        <div class="list-group list-group-flush">
                            <?php foreach ($group['results'] as $result): ?>
                                <a href="<?= e($result['url']) ?>" class="list-group-item list-group-item-action d-flex align-items-start gap-3 py-3">
                                    <i class="fa-solid <?= e($result['icon'] ?? $group['icon']) ?> text-muted mt-1"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">
                                            <?= search_highlight($result['title'], $q) ?>
                                            <?php if (!empty($result['badge'])): ?>
                                                <span class="badge bg-warning text-dark ms-1"><?= e($result['badge']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($result['subtitle'])): ?>
                                            <small class="text-muted text-truncate d-block"><?= search_highlight($result['subtitle'], $q) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-magnifying-glass fa-3x mb-3 opacity-25"></i>
                <p class="mb-0"><?= e(t('home.search.start_hint')) ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php $view->end(); ?>
