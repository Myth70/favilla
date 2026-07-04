<?php
/**
 * @var array $runs
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 */
?>
<?php if (empty($runs)): ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-info-circle me-1"></i><?= e(t('healthcheck.history.empty')) ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3"><?= e(t('healthcheck.history.col_data')) ?></th>
                        <th class="text-center"><?= e(t('healthcheck.history.col_ok')) ?></th>
                        <th class="text-center"><?= e(t('healthcheck.history.col_warn')) ?></th>
                        <th class="text-center"><?= e(t('healthcheck.history.col_fail')) ?></th>
                        <th><?= e(t('healthcheck.history.col_executed_by')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td class="ps-3"><?= e(format_date($run['created_at'], 'long')) ?></td>
                        <td class="text-center">
                            <span class="hc-history-badge hc-ok"><?= e($run['total_ok']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="hc-history-badge hc-warn"><?= e($run['total_warn']) ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$run['total_fail'] > 0): ?>
                                <span class="hc-history-badge hc-fail"><?= e($run['total_fail']) ?></span>
                            <?php else: ?>
                                <span class="hc-history-badge hc-ok">0</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($run['user_name'] ?? t('healthcheck.history.system')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link"
                       href="<?= e(route('healthcheck.history')) ?>?page=<?= $p ?>"
                       hx-get="<?= e(route('healthcheck.history')) ?>?page=<?= $p ?>"
                       hx-target="#hc-history"
                       hx-push-url="true">
                        <?= $p ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>
