<?php
/**
 * Partial: mime_table — reusable MIME table fragment for HTMX swap.
 * Variabili: $mimeTypes (array of ['mime' => string, 'abilitato' => bool, 'label' => ?string])
 */
?>
<?php if (empty($mimeTypes)): ?>
    <?php $view->include('Documenti/Views/partials/empty_state', [
        'icon'      => 'fa-file-code',
        'titolo'    => t('documenti.admin_mime.empty_titolo'),
        'messaggio' => t('documenti.admin_mime.empty_messaggio'),
    ]); ?>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= e(t('documenti.admin_mime.tipo_mime_col')) ?></th>
                    <th><?= e(t('documenti.admin_mime.stato_col')) ?></th>
                    <th class="text-end" style="width:9rem"><?= e(t('documenti.admin_sequenze.azione_col')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mimeTypes as $mt):
                    $mime    = (string) $mt['mime'];
                    $label   = (string) ($mt['label'] ?? '');
                    $enabled = !empty($mt['abilitato']);
                    ?>
                <tr data-mime="<?= e(strtolower($mime . ' ' . $label)) ?>">
                    <td>
                        <code class="dc-code"><?= e($mime) ?></code>
                        <?php if ($label !== ''): ?>
                            <small class="text-muted ms-2"><?= e($label) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($enabled): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-check me-1" aria-hidden="true"></i><?= e(t('documenti.admin_mime.abilitato')) ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="fa-solid fa-ban me-1" aria-hidden="true"></i><?= e(t('documenti.admin_mime.disabilitato')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php $confirmMsg = $enabled
                                ? t('documenti.admin_mime.disabilita_confirm', ['mime' => $mime])
                                : t('documenti.admin_mime.abilita_confirm', ['mime' => $mime]); ?>
                        <form method="post"
                            action="<?= e(route('documenti.admin.mime.toggle', ['mime' => rawurlencode($mime)])) ?>"
                            hx-post="<?= e(route('documenti.admin.mime.toggle', ['mime' => rawurlencode($mime)])) ?>"
                            hx-target="#mime-table-container"
                            hx-swap="innerHTML"
                            data-app-confirm="<?= e($confirmMsg) ?>"
                            class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit"
                                    class="btn btn-sm <?= $enabled ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                    hx-disabled-elt="this">
                                <?php if ($enabled): ?>
                                    <i class="fa-solid fa-ban me-1" aria-hidden="true"></i><?= e(t('documenti.admin_mime.disabilita_btn')) ?>
                                <?php else: ?>
                                    <i class="fa-solid fa-check me-1" aria-hidden="true"></i><?= e(t('documenti.admin_mime.abilita_btn')) ?>
                                <?php endif; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
