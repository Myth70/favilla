<?php
$filters = $filters ?? [];
$logs = $logs ?? [];
$page = $page ?? 1;
$pages = $pages ?? 1;
$total = $total ?? 0;

$sh = sort_context(
    $filters['sort'] ?? 'created_at',
    $filters['dir'] ?? 'DESC',
    $filters,
    route('admin.mail.log.table'),
    '#mail-log-table'
);
?>

<!-- Filters -->
<div class="row mb-3 g-2">
    <div class="col-md-4">
        <input type="text" class="form-control form-control-sm" name="q"
               placeholder="<?= e(t('admin.mail.log.search_ph')) ?>"
               value="<?= e($filters['q'] ?? '') ?>"
               hx-get="<?= e(route('admin.mail.log.table')) ?>"
               hx-trigger="keyup changed delay:400ms"
               hx-target="#mail-log-table"
               hx-include="[name='status']">
    </div>
    <div class="col-md-3">
        <select class="form-select form-select-sm" name="status"
                hx-get="<?= e(route('admin.mail.log.table')) ?>"
                hx-trigger="change"
                hx-target="#mail-log-table"
                hx-include="[name='q']">
            <option value=""><?= e(t('admin.mail.log.all_statuses')) ?></option>
            <option value="sent" <?= ($filters['status'] ?? '') === 'sent' ? 'selected' : '' ?>><?= e(t('admin.mail.log.st_sent')) ?></option>
            <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>><?= e(t('admin.mail.log.st_failed')) ?></option>
            <option value="logged" <?= ($filters['status'] ?? '') === 'logged' ? 'selected' : '' ?>><?= e(t('admin.mail.log.st_logged')) ?></option>
        </select>
    </div>
    <div class="col-md-2 text-end">
        <small class="text-muted"><?= e(t('admin.mail.log.results', ['count' => $total])) ?></small>
    </div>
</div>

<?php if (empty($logs)): ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-info-circle me-1"></i><?= e(t('admin.mail.log.empty')) ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm adm-table">
            <thead>
                <tr>
                    <th><?= $sh('created_at', t('admin.mail.log.col_date')) ?></th>
                    <th><?= $sh('to_email', t('admin.mail.log.col_to')) ?></th>
                    <th><?= $sh('subject', t('admin.mail.log.col_subject')) ?></th>
                    <th><?= e(t('admin.mail.log.col_template')) ?></th>
                    <th><?= $sh('status', t('admin.mail.log.col_status')) ?></th>
                    <th><?= e(t('admin.mail.log.col_user')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="text-nowrap"><?= format_date_it($log['created_at'], 'short') ?> <?= format_date_it($log['created_at'], 'time') ?></td>
                    <td><?= e($log['to_email']) ?></td>
                    <td><?= e(mb_strimwidth($log['subject'], 0, 50, '...')) ?></td>
                    <td>
                        <?php if ($log['template']): ?>
                            <code><?= e($log['template']) ?></code>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $statusClass = match ($log['status']) {
                            'sent'   => 'success',
                            'failed' => 'danger',
                            default  => 'secondary',
                        };
                        ?>
                        <span class="badge bg-<?= $statusClass ?>"><?= e($log['status']) ?></span>
                        <?php if ($log['error']): ?>
                            <i class="fa-solid fa-circle-exclamation text-danger ms-1"
                               title="<?= e($log['error']) ?>"
                               data-bs-toggle="tooltip"></i>
                        <?php endif; ?>
                    </td>
                    <td><?= e($log['created_by_name'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm justify-content-center">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="#"
                           hx-get="<?= e(route('admin.mail.log.table')) ?>?page=<?= $i ?>&<?= http_build_query(array_filter($filters, fn($v) => $v !== '')) ?>"
                           hx-target="#mail-log-table">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
