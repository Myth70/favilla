<?php if (empty($templates)): ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-info-circle me-1"></i><?= e(t('admin.mail.tpl_empty')) ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover adm-table">
            <thead>
                <tr>
                    <th><?= e(t('admin.mail.col_name')) ?></th>
                    <th><?= e(t('admin.mail.col_slug')) ?></th>
                    <th><?= e(t('admin.mail.col_subject')) ?></th>
                    <th><?= e(t('admin.mail.col_variables')) ?></th>
                    <th><?= e(t('admin.mail.col_updated')) ?></th>
                    <th class="text-end"><?= e(t('admin.mail.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $tpl): ?>
                <tr>
                    <td><strong><?= e($tpl['name']) ?></strong></td>
                    <td><code><?= e($tpl['slug']) ?></code></td>
                    <td><?= e($tpl['subject']) ?></td>
                    <td>
                        <?php if ($tpl['variables']): ?>
                            <?php foreach (explode(',', $tpl['variables']) as $var): ?>
                                <span class="badge border adm-var-badge"><?= e(trim($var)) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= format_date_it($tpl['updated_at']) ?></td>
                    <td class="text-end">
                        <?php if (has_permission('admin.mail.manage')): ?>
                            <a href="<?= e(route('admin.mail.templates.edit', ['id' => $tpl['id']])) ?>"
                               class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="<?= e(t('admin.mail.edit_tip')) ?>">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST"
                                  action="<?= e(route('admin.mail.templates.destroy', ['id' => $tpl['id']])) ?>"
                                  class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="<?= e(t('admin.mail.delete_tip')) ?>"
                                        data-app-confirm="<?= e(t('admin.mail.delete_confirm')) ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
