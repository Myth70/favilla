<?php
/**
 * Partial: tabella backup su filesystem
 * Variabili: $backups (array), $view
 */
?>
<?php if (empty($backups)): ?>
    <div class="text-center text-muted py-4">
        <i class="fa-solid fa-database fa-2x mb-3 d-block opacity-50"></i>
        <?= e(t('backup.empty')) ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= e(t('backup.cols.filename')) ?></th>
                    <th><?= e(t('backup.cols.size')) ?></th>
                    <th><?= e(t('backup.cols.created_date')) ?></th>
                    <th class="text-end"><?= e(t('common.label.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td>
                            <i class="fa-solid fa-file-zipper text-secondary me-2"></i>
                            <code><?= e($backup['filename']) ?></code>
                        </td>
                        <td><?= e(number_format($backup['size'] / 1048576, 2, ',', '.')) ?> MB</td>
                        <td><?= e($backup['date']) ?></td>
                        <td class="text-end">
                            <a href="<?= e(route('backup.download')) ?>?file=<?= urlencode($backup['filename']) ?>"
                               class="btn btn-sm btn-outline-primary me-1"
                               data-bs-toggle="tooltip"
                               title="<?= e(t('backup.action.download')) ?>"
                               aria-label="<?= e(t('backup.action.download')) ?>">
                                <i class="fa-solid fa-download"></i>
                            </a>
                            <?php if (has_permission('backup.restore')): ?>
                                <span class="d-inline-flex" data-bs-toggle="tooltip" title="<?= e(t('backup.action.restore')) ?>">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-warning me-1"
                                            aria-label="<?= e(t('backup.action.restore')) ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#backupRestoreModal"
                                            data-backup-file="<?= e($backup['filename']) ?>">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                </span>
                            <?php endif; ?>
                            <form method="POST" action="<?= e(route('backup.destroy')) ?>"
                                  class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="file" value="<?= e($backup['filename']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="<?= e(t('common.action.delete')) ?>" aria-label="<?= e(t('common.action.delete')) ?>"
                                        data-app-confirm="<?= e(t('backup.delete_confirm')) ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (has_permission('backup.restore')): ?>
        <div class="modal fade" id="backupRestoreModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="<?= e(route('backup.restore')) ?>">
                        <?= csrf_field() ?>
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fa-solid fa-triangle-exclamation text-warning"></i><?= e(t('backup.restore_modal.title')) ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2"><?= e(t('backup.restore_modal.about')) ?></p>
                            <p class="mb-3"><code id="backup-restore-filename">-</code></p>

                            <input type="hidden" name="file" id="backup-restore-file-input" value="">

                            <div class="mb-3">
                                <label for="backup-confirm-text" class="form-label"><?= t('backup.restore_modal.confirm_instruction', ['keyword' => e(t('backup.restore_keyword'))]) ?></label>
                                <input type="text" class="form-control" id="backup-confirm-text" name="confirm_text" required>
                            </div>

                            <div class="mb-1">
                                <label for="backup-current-password" class="form-label"><?= e(t('backup.restore_modal.password_label')) ?></label>
                                <input type="password" class="form-control" id="backup-current-password" name="current_password" required autocomplete="current-password">
                            </div>

                            <small class="text-muted"><?= e(t('backup.restore_modal.safety_note')) ?></small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.action.cancel')) ?></button>
                            <button type="submit" class="btn btn-warning">
                                <i class="fa-solid fa-rotate-left me-1"></i><?= e(t('backup.action.restore_now')) ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
        (function () {
            'use strict';

            var modal = document.getElementById('backupRestoreModal');
            if (!modal) {
                return;
            }

            modal.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                var file = trigger ? trigger.getAttribute('data-backup-file') : '';

                var fileInput = document.getElementById('backup-restore-file-input');
                var fileText = document.getElementById('backup-restore-filename');
                var confirmInput = document.getElementById('backup-confirm-text');
                var passInput = document.getElementById('backup-current-password');

                if (fileInput) {
                    fileInput.value = file || '';
                }
                if (fileText) {
                    fileText.textContent = file || '-';
                }
                if (confirmInput) {
                    confirmInput.value = '';
                }
                if (passInput) {
                    passInput.value = '';
                }
            });
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>
