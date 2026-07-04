<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="app-card-icon"><i class="fa-solid fa-layer-group"></i></span>
                <span class="fw-semibold"><?= e(t('notifications.admin.queue_stats')) ?></span>
            </div>
            <div class="card-body">
                <?php foreach (($queueStats ?? []) as $channelSlug => $stats): ?>
                <div class="ntas-status-block">
                    <div class="ntas-status-title"><?= e($channelSlug) ?></div>
                    <div class="ntas-status-pills">
                        <?php foreach ($stats as $status => $count): ?>
                            <span class="badge rounded-pill text-bg-light border"><?= e($status) ?>: <?= e((string) $count) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <hr>

                <?php foreach (($deliveryStats ?? []) as $channelSlug => $stats): ?>
                <div class="ntas-status-block">
                    <div class="ntas-status-title"><?= e(t('notifications.admin.delivery_prefix', ['channel' => $channelSlug])) ?></div>
                    <div class="ntas-status-pills">
                        <?php foreach ($stats as $status => $count): ?>
                            <span class="badge rounded-pill text-bg-light border"><?= e($status) ?>: <?= e((string) $count) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <span class="fw-semibold"><?= e(t('notifications.admin.queue_recent')) ?></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge text-bg-light border"><?= e(t('notifications.admin.queue_items', ['count' => count($recentQueue ?? [])])) ?></span>
                    <?php
                    $hasFailedItems = false;
                    foreach (($recentQueue ?? []) as $_qRow) {
                        if (($_qRow['status'] ?? '') === 'failed') { $hasFailedItems = true; break; }
                    }
                    ?>
                    <?php if ($hasFailedItems): ?>
                    <form method="POST" action="<?= e(route('admin.notifications.queue.retry-all')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-warning btn-sm" title="<?= e(t('notifications.admin.retry_all_tip')) ?>">
                            <i class="fa-solid fa-rotate me-1"></i><?= e(t('notifications.admin.retry_all')) ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= e(t('notifications.admin.qcol_channel')) ?></th>
                            <th><?= e(t('notifications.admin.qcol_module')) ?></th>
                            <th><?= e(t('notifications.admin.qcol_user')) ?></th>
                            <th><?= e(t('notifications.admin.qcol_status')) ?></th>
                            <th><?= e(t('notifications.admin.qcol_attempts')) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentQueue)): ?>
                            <?php foreach ($recentQueue as $row): ?>
                            <tr>
                                <td><?= (int) $row['id'] ?></td>
                                <td><?= e($row['channel_slug']) ?></td>
                                <td><?= e($row['source_module']) ?></td>
                                <td><?= e($row['user_name'] ?? ('#' . $row['user_id'])) ?></td>
                                <td><span class="badge text-bg-light border"><?= e($row['status']) ?></span></td>
                                <td><?= (int) $row['attempts'] ?>/<?= (int) $row['max_attempts'] ?></td>
                                <td>
                                    <?php if (($row['status'] ?? '') === 'failed'): ?>
                                    <form method="POST" action="<?= e(route('admin.notifications.queue.retry', ['id' => $row['id']])) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-warning btn-sm py-0 px-1" title="<?= e(t('notifications.admin.retry')) ?>">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4"><?= e(t('notifications.admin.queue_empty')) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
