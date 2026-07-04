<?php
$severityBadge = [
    'critical' => 'bg-danger',
    'high'     => 'bg-warning text-dark',
    'medium'   => 'bg-info text-dark',
    'low'      => 'bg-secondary',
];
// Etichette severità/tipo dalle mappe condivise admin.security.*; fallback al valore grezzo.
$sevLabel = static function (string $sev): string {
    $label = t('admin.security.severity.' . $sev);
    return $label === 'admin.security.severity.' . $sev ? $sev : $label;
};
$typLabel = static function (string $type): string {
    $label = t('admin.security.incident_type.' . $type);
    return $label === 'admin.security.incident_type.' . $type ? $type : $label;
};
?>

<div class="card adm-card">
    <div class="table-responsive">
        <table class="table adm-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(t('admin.security.incidents.col_date')) ?></th>
                    <th><?= e(t('admin.security.incidents.col_type')) ?></th>
                    <th><?= e(t('admin.security.incidents.col_severity')) ?></th>
                    <th>IP</th>
                    <th><?= e(t('admin.security.incidents.col_user')) ?></th>
                    <th><?= e(t('admin.security.incidents.col_details')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($incidents)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fa-solid fa-shield-check fa-2x mb-2 d-block"></i>
                            <?= e(t('admin.security.incidents.empty')) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($incidents as $inc): ?>
                        <tr>
                            <td class="text-nowrap">
                                <small><?= e(date('d/m/Y H:i', strtotime($inc['created_at']))) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-dark">
                                    <?= e($typLabel($inc['type'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $severityBadge[$inc['severity']] ?? 'bg-secondary' ?>">
                                    <?= e($sevLabel($inc['severity'])) ?>
                                </span>
                            </td>
                            <td>
                                <code class="small"><?= e($inc['ip'] ?? '—') ?></code>
                            </td>
                            <td>
                                <?= e($inc['user_name'] ?? '—') ?>
                            </td>
                            <td>
                                <?php
                                $details = json_decode($inc['details'] ?? '{}', true);
                                if (is_array($details)):
                                    $items = [];
                                    foreach ($details as $k => $v) {
                                        if (!is_array($v)) {
                                            $items[] = e($k) . ': ' . e((string)$v);
                                        }
                                    }
                                    echo '<small class="text-muted">' . implode(' · ', $items) . '</small>';
                                endif;
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
            <small class="text-muted"><?= e(t('admin.security.incidents.total_label', ['count' => $total])) ?></small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link"
                               href="<?= e(route('admin.security.incidents')) ?>?page=<?= $p ?>&type=<?= e($filters['type'] ?? '') ?>&severity=<?= e($filters['severity'] ?? '') ?>"
                               hx-get="<?= e(route('admin.security.incidents')) ?>?page=<?= $p ?>&type=<?= e($filters['type'] ?? '') ?>&severity=<?= e($filters['severity'] ?? '') ?>"
                               hx-target="#incidents-table"
                               hx-push-url="true">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
