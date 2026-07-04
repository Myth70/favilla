<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php
use App\Modules\Documenti\Helpers\StatoHelper;

/**
 * Renderizza un diff chiave-per-chiave tra due JSON (old, new).
 * @return string HTML
 */
$renderDiff = function (?string $oldJson, ?string $newJson): string {
    $old = $oldJson ? json_decode($oldJson, true) : [];
    $new = $newJson ? json_decode($newJson, true) : [];
    if (!is_array($old)) {
        $old = [];
    }
    if (!is_array($new)) {
        $new = [];
    }
    $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
    sort($keys);
    $out = '<div class="dc-diff">';
    foreach ($keys as $k) {
        $oldVal = array_key_exists($k, $old) ? $old[$k] : null;
        $newVal = array_key_exists($k, $new) ? $new[$k] : null;
        if ($oldVal === $newVal) {
            continue;
        }
        $kEsc = htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8');
        if ($oldVal !== null) {
            $out .= '<div class="dc-diff-line dc-diff-del">- <span class="dc-diff-key">' . $kEsc . '</span>: '
                  . htmlspecialchars(is_scalar($oldVal) ? (string) $oldVal : json_encode($oldVal, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')
                  . '</div>';
        }
        if ($newVal !== null) {
            $out .= '<div class="dc-diff-line dc-diff-add">+ <span class="dc-diff-key">' . $kEsc . '</span>: '
                  . htmlspecialchars(is_scalar($newVal) ? (string) $newVal : json_encode($newVal, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')
                  . '</div>';
        }
    }
    if ($out === '<div class="dc-diff">') {
        $out .= '<div class="text-muted small">' . e(t('documenti.admin_audit_dettaglio.nessuna_variazione')) . '</div>';
    }
    $out .= '</div>';
    return $out;
};
?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php $view->include('partials/pf-hero-module', [
            'moduleName'     => t('documenti.admin_audit_dettaglio.hero_title', ['entity' => $entity, 'id' => (int) $entityId]),
            'moduleIcon'     => 'fa-solid fa-scroll',
            'moduleSubtitle' => t('documenti.admin_audit_dettaglio.subtitle'),
            'moduleButtons'  => '<a href="' . e(route('documenti.admin.audit')) . '" class="btn btn-sm btn-outline-secondary">'
                              . '<i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>' . e(t('documenti.admin_audit_dettaglio.torna_al_log')) . '</a>',
        ]); ?>
    </div>

    <div class="col-12">
        <?php if (empty($logs)): ?>
            <?php $view->include('Documenti/Views/partials/empty_state', [
                'icon'      => 'fa-clipboard',
                'titolo'    => t('documenti.admin_audit_dettaglio.empty_titolo'),
                'messaggio' => t('documenti.admin_audit_dettaglio.empty_messaggio'),
            ]); ?>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-top">
                    <thead class="table-light">
                        <tr>
                            <th style="width:8rem"><?= e(t('documenti.admin_audit.azione_label')) ?></th>
                            <th style="width:10rem"><?= e(t('documenti.admin_elenco.owner_col')) ?></th>
                            <th><?= e(t('documenti.admin_audit_dettaglio.modifiche_col')) ?></th>
                            <th style="width:10rem"><?= e(t('documenti.admin_audit.data_col')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $i => $log): ?>
                        <tr>
                            <td><?= StatoHelper::azioneAuditBadge((string) $log['action']) ?></td>
                            <td>
                                <small><?= e($log['user_name'] ?? ($log['user_id'] ? t('documenti.timeline.utente_fallback', ['id' => $log['user_id']]) : '—')) ?></small>
                                <?php if (!empty($log['ip'])): ?>
                                    <small class="d-block text-muted"><?= e($log['ip']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($log['old_value']) || !empty($log['new_value'])): ?>
                                    <details>
                                        <summary class="fw-semibold small">
                                            <i class="fa-solid fa-code-compare me-1" aria-hidden="true"></i><?= e(t('documenti.admin_audit_dettaglio.visualizza_diff')) ?>
                                        </summary>
                                        <div class="d-flex justify-content-end gap-2 my-2">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    data-copy-target="#dc-diff-raw-<?= (int) $i ?>"
                                                    title="<?= e(t('documenti.admin_audit_dettaglio.copia_json_tip')) ?>">
                                                <i class="fa-solid fa-copy me-1" aria-hidden="true"></i><?= e(t('documenti.admin_audit_dettaglio.copia_btn')) ?>
                                            </button>
                                        </div>
                                        <?= $renderDiff($log['old_value'] ?? null, $log['new_value'] ?? null) ?>
                                        <textarea id="dc-diff-raw-<?= (int) $i ?>" class="visually-hidden" readonly><?= e(json_encode([
                                            'before' => json_decode($log['old_value'] ?? 'null', true),
                                            'after'  => json_decode($log['new_value'] ?? 'null', true),
                                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
                                    </details>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small data-bs-toggle="tooltip" title="<?= e(format_date($log['created_at'], 'long')) ?>">
                                    <?= e(format_date($log['created_at'], 'compact')) ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>
<?php $view->end(); ?>
