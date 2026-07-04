<?php
/**
 * VISTA DETTAGLIO — Mostra un singolo record.
 *
 * Layout a 2 colonne (4+8): info + contenuto principale.
 * Pattern copiato da Admin/Views/users/show.php.
 *
 * i18n: ogni stringa user-facing passa da e(t('example.<chiave>')).
 */

$view->layout('main');

$statusColors = ['active' => 'success', 'inactive' => 'secondary', 'archived' => 'warning'];
$statusKey    = (string) ($item['status'] ?? 'active');
$statusColor  = $statusColors[$statusKey] ?? 'secondary';
$statusLabel  = t('example.status.' . $statusKey);

$heroButtons = '';
if (has_permission('example.edit')) {
    $heroButtons .= '<a href="' . e(route('example.edit', ['id' => $item['id']])) . '" class="btn btn-sm btn-outline-primary">'
                  . '<i class="fa-solid fa-pen me-1"></i>' . e(t('example.actions.edit')) . '</a>';
}
$heroButtons .= ($heroButtons !== '' ? ' ' : '')
             . '<a href="' . e(route('example.index')) . '" class="btn btn-sm btn-outline-secondary">'
             . '<i class="fa-solid fa-arrow-left me-1"></i>' . e(t('example.actions.back')) . '</a>';

$heroSubtitle = !empty($item['email'])
    ? e((string) $item['email']) . ' &middot; ' . e($statusLabel)
    : e(t('example.detail.subtitle_fallback'));
?>

<?php $view->start('content'); ?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-module', [
        'moduleName'     => $item['name'] ?? t('example.title'),
        'moduleIcon'     => 'fa-solid fa-cube',
        'moduleSubtitle' => $heroSubtitle,
        'moduleButtons'  => $heroButtons,
    ]); ?>

    <div class="row g-4">

        <!-- ── Colonna sinistra: info + azioni ─────────────────── -->
        <div class="col-lg-4">
            <!-- Card info -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-circle-info"></i></span>
                    <span class="fw-semibold"><?= e(t('example.sections.info')) ?></span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted"><?= e(t('example.fields.id')) ?></dt>
                        <dd class="col-7">#<?= e((string) $item['id']) ?></dd>

                        <dt class="col-5 text-muted"><?= e(t('example.fields.status')) ?></dt>
                        <dd class="col-7">
                            <span class="badge bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
                        </dd>

                        <dt class="col-5 text-muted"><?= e(t('example.fields.email')) ?></dt>
                        <dd class="col-7"><?= e($item['email'] ?? '—') ?></dd>

                        <dt class="col-5 text-muted"><?= e(t('example.fields.author')) ?></dt>
                        <dd class="col-7"><?= e($item['author_name'] ?? '—') ?></dd>

                        <dt class="col-5 text-muted"><?= e(t('example.fields.created_at')) ?></dt>
                        <dd class="col-7"><?= e(format_date_it($item['created_at'] ?? '', 'long')) ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Card azioni -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-cog"></i></span>
                    <span class="fw-semibold"><?= e(t('example.sections.actions')) ?></span>
                </div>
                <div class="card-body d-grid gap-2">
                    <?php if (has_permission('example.edit')): ?>
                    <a href="<?= e(route('example.edit', ['id' => $item['id']])) ?>"
                       class="btn btn-outline-primary">
                        <i class="fa-solid fa-pen me-1"></i> <?= e(t('example.actions.edit')) ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?= e(route('example.index')) ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> <?= e(t('example.actions.back')) ?>
                    </a>
                </div>
            </div>

            <!-- Card pericolo -->
            <?php if (has_permission('example.delete')): ?>
            <div class="card border-danger">
                <div class="card-header d-flex align-items-center gap-2 text-danger">
                    <span class="app-card-icon"><i class="fa-solid fa-triangle-exclamation text-danger"></i></span>
                    <span class="fw-semibold"><?= e(t('example.sections.danger_zone')) ?></span>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= e(route('example.destroy', ['id' => $item['id']])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-outline-danger w-100"
                                data-app-confirm="<?= e(t('example.confirm.delete')) ?>">
                            <i class="fa-solid fa-trash me-1"></i> <?= e(t('example.actions.delete')) ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Colonna destra: contenuto ───────────────────────── -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-align-left"></i></span>
                    <span class="fw-semibold"><?= e(t('example.sections.description')) ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($item['description'])): ?>
                        <p class="mb-0"><?= nl2br(e($item['description'])) ?></p>
                    <?php else: ?>
                        <p class="text-muted fst-italic mb-0"><?= e(t('example.detail.no_description')) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($item['updated_at'])): ?>
                <div class="card-footer text-muted small">
                    <?= e(t('example.detail.last_update')) ?> <?= e(format_date_it($item['updated_at'] ?? '', 'long')) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php $view->end(); ?>
