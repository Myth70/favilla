<?php $totalPages = max(1, (int) ceil($total / $perPage)); ?>

<?php if (empty($conversations)): ?>
    <div class="text-center text-muted py-5">
        <i class="fa fa-comments fa-2x mb-2 d-block opacity-50"></i>
        <?= e(t('teams.admin.no_conversations_found')) ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-muted fw-normal ps-3 tm-col-id">#</th>
                    <th class="text-muted fw-normal tm-col-type"><?= e(t('teams.admin.col_type')) ?></th>
                    <th class="text-muted fw-normal"><?= e(t('teams.admin.col_name_participants')) ?></th>
                    <th class="text-muted fw-normal text-center tm-col-members"><?= e(t('teams.group_panel.tab_members')) ?></th>
                    <th class="text-muted fw-normal text-center tm-col-messages"><?= e(t('teams.title')) ?></th>
                    <th class="text-muted fw-normal tm-col-updated"><?= e(t('teams.admin.col_last_activity')) ?></th>
                    <th class="text-muted fw-normal tm-col-status"><?= e(t('teams.admin.col_status')) ?></th>
                    <th class="text-muted fw-normal text-end pe-3 tm-col-actions"><?= e(t('teams.admin.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversations as $conv): ?>
                <tr>
                    <td class="text-muted small align-middle ps-3"><?= e($conv['id']) ?></td>
                    <td class="align-middle">
                        <?php if ($conv['type'] === 'direct'): ?>
                            <span class="badge bg-secondary"><?= e(t('teams.admin.type_direct')) ?></span>
                        <?php else: ?>
                            <span class="badge bg-primary"><?= e(t('teams.admin.type_group')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle">
                        <?php if ($conv['type'] === 'group' && $conv['name']): ?>
                            <span class="fw-medium"><?= e($conv['name']) ?></span>
                            <?php if ($conv['member_names']): ?>
                                <br><small class="text-muted"><?= e($conv['member_names']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted"><?= e($conv['member_names'] ?? '—') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center align-middle"><?= e($conv['member_count']) ?></td>
                    <td class="text-center align-middle"><?= e($conv['message_count']) ?></td>
                    <td class="align-middle">
                        <small class="text-muted"><?= e(format_date($conv['updated_at'], 'relative')) ?></small>
                    </td>
                    <td class="align-middle">
                        <?php if ($conv['archived_at']): ?>
                            <span class="badge bg-secondary"><?= e(t('teams.admin.status_archived')) ?></span>
                        <?php else: ?>
                            <span class="badge bg-success"><?= e(t('teams.admin.status_active')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end align-middle pe-3">
                        <?php if (!$conv['archived_at']): ?>
                            <button class="btn btn-sm btn-outline-warning"
                                    hx-post="<?= e(route('teams.admin.archive', ['id' => $conv['id']])) ?>"
                                    hx-target="#tm-admin-table"
                                    hx-swap="innerHTML"
                                    hx-include="#tm-admin-filter"
                                    data-app-confirm="<?= e(t('teams.group_panel.confirm_archive')) ?>"
                                    data-app-confirm-label="<?= e(t('teams.group_panel.archive_confirm_label')) ?>"
                                    data-app-confirm-class="btn-warning">
                                <i class="fa fa-box-archive"></i> <?= e(t('teams.group_panel.archive_confirm_label')) ?>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-danger"
                                    hx-delete="<?= e(route('teams.admin.destroy', ['id' => $conv['id']])) ?>"
                                    hx-target="#tm-admin-table"
                                    hx-swap="innerHTML"
                                    hx-include="#tm-admin-filter"
                                    data-app-confirm="<?= e(t('teams.admin.confirm_destroy')) ?>"
                                    data-app-confirm-label="<?= e(t('teams.admin.destroy_confirm_label')) ?>">
                                <i class="fa fa-trash"></i> <?= e(t('teams.message.delete_btn')) ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
        <small class="text-muted"><?= e(t('teams.admin.pagination_info', ['page' => $page, 'pages' => $totalPages, 'total' => $total])) ?></small>
        <div class="d-flex gap-1">
            <?php if ($page > 1): ?>
                <button class="btn btn-sm btn-outline-secondary"
                        hx-get="<?= e(route('teams.admin.conversations')) ?>"
                        hx-vals='<?= e(json_encode(['page' => $page - 1, 'search' => $search, 'filter' => $filter])) ?>'
                        hx-target="#tm-admin-table"
                        hx-swap="innerHTML">
                    <i class="fa fa-chevron-left"></i> <?= e(t('teams.admin.prev_btn')) ?>
                </button>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <button class="btn btn-sm btn-outline-secondary"
                        hx-get="<?= e(route('teams.admin.conversations')) ?>"
                        hx-vals='<?= e(json_encode(['page' => $page + 1, 'search' => $search, 'filter' => $filter])) ?>'
                        hx-target="#tm-admin-table"
                        hx-swap="innerHTML">
                    <?= e(t('teams.admin.next_btn')) ?> <i class="fa fa-chevron-right"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>
