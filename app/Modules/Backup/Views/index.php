<?php
/** @var \App\Core\View $view */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->start('content');

$backupAction = '<form method="POST" action="' . e(route('backup.store')) . '">';
$backupAction .= csrf_field();
$backupAction .= '<button type="submit" class="btn btn-primary btn-sm" ' . ($isRunning ? 'disabled' : '') . ' data-bs-toggle="tooltip" title="' . e(t('backup.start_tooltip')) . '" data-app-confirm="' . e(t('backup.start_confirm')) . '" data-app-confirm-label="' . e(t('backup.action.start')) . '" data-app-confirm-class="btn-primary"><i class="fa-solid fa-plus me-1"></i>' . e(t('backup.action.create')) . '</button></form>';
?>

<div class="container-fluid">

    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid fa-database',
        'adminTitle'    => t('backup.hero_title'),
        'adminSubtitle' => t('backup.hero_subtitle'),
        'adminButtons'  => $backupAction,
    ]); ?>

    <!-- Backup in corso -->
    <?php if ($isRunning): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
            <i class="fa-solid fa-spinner fa-spin mt-1 flex-shrink-0"></i>
            <div>
                <strong><?= e(t('backup.running_title')) ?></strong> <?= e(t('backup.running_body')) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Avvisi -->
    <div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="fa-solid fa-circle-info mt-1 flex-shrink-0"></i>
        <div>
            <strong><?= e(t('backup.note_label')) ?></strong> <?= t('backup.note_body', ['count' => (int) $maxCount]) ?>
            <?php if (!empty($excludedTables)): ?>
                <br><strong><?= e(t('backup.excluded_label')) ?></strong>
                <?php foreach ($excludedTables as $t): ?>
                    <code><?= e($t) ?></code><?php if ($t !== end($excludedTables)) echo ', '; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabella backup su filesystem -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <span class="app-card-icon"><i class="fa-solid fa-folder-open"></i></span>
            <span class="fw-semibold"><?= e(t('backup.available')) ?></span>
            <span class="badge bg-secondary ms-auto"><?= count($backups) ?></span>
        </div>
        <div class="card-body p-0">
            <?php $view->include('Backup/Views/partials/backup_table', ['backups' => $backups]); ?>
        </div>
    </div>

    <!-- Storico creazioni (da DB) -->
    <?php if (!empty($history)): ?>
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center gap-2">
            <span class="app-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <span class="fw-semibold"><?= e(t('backup.history')) ?></span>
            <span class="text-muted small ms-2"><?= e(t('backup.history_hint')) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= e(t('backup.cols.file')) ?></th>
                            <th><?= e(t('backup.cols.size')) ?></th>
                            <th><?= e(t('backup.cols.tables')) ?></th>
                            <th><?= e(t('backup.cols.database')) ?></th>
                            <th><?= e(t('backup.cols.created_by')) ?></th>
                            <th><?= e(t('backup.cols.date')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <?php
                                $dbs = [];
                                $hasPartial = false;
                                if (!empty($h['databases_json'])) {
                                    $decoded = json_decode((string) $h['databases_json'], true);
                                    if (is_array($decoded)) {
                                        foreach ($decoded as $d) {
                                            $ok = !empty($d['usable']);
                                            if (!$ok) {
                                                $hasPartial = true;
                                            }
                                            $dbs[] = [
                                                'label' => (string) ($d['key'] ?? $d['database_name'] ?? '?'),
                                                'ok'    => $ok,
                                            ];
                                        }
                                    }
                                }
                            ?>
                            <tr>
                                <td><code class="small"><?= e($h['filename']) ?></code></td>
                                <td><?= e(number_format($h['size_bytes'] / 1048576, 2, ',', '.')) ?> MB</td>
                                <td><?= (int) $h['table_count'] ?></td>
                                <td>
                                    <?php if (empty($dbs)): ?>
                                        <span class="text-muted small">—</span>
                                    <?php else: ?>
                                        <?php foreach ($dbs as $d): ?>
                                            <span class="badge <?= $d['ok'] ? 'bg-secondary' : 'bg-warning text-dark' ?> me-1"><?= e($d['label']) ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($hasPartial): ?>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= e(t('backup.partial')) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($h['created_by_name'] ?? '—') ?></td>
                                <td><?= e(format_date($h['created_at'], 'long')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php $view->end(); ?>
