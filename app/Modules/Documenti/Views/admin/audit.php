<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php
use App\Modules\Documenti\Helpers\StatoHelper;
use App\Modules\Documenti\Helpers\UiHelper;

$filters  = $filters  ?? [];
$entities = $entities ?? [];
$actions  = $actions  ?? [];
$logs     = $logs     ?? [];
$page     = (int) ($page  ?? 1);
$pages    = (int) ($pages ?? 1);
$total    = (int) ($total ?? 0);

$exportQs = http_build_query(array_filter($filters, static fn ($v) => $v !== '' && $v !== null));
?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php
        $heroButtons = '<a href="' . e(route('documenti.admin.audit.export')) . ($exportQs ? '?' . $exportQs : '') . '" class="btn btn-sm btn-outline-secondary">'
                     . '<i class="fa-solid fa-file-csv me-1" aria-hidden="true"></i>' . e(t('documenti.admin_audit.esporta_csv_btn')) . '</a>';
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('documenti.admin_audit.hero_title'),
    'moduleIcon'     => 'fa-solid fa-scroll',
    'moduleSubtitle' => t('documenti.admin_audit.subtitle'),
    'moduleButtons'  => $heroButtons,
]); ?>
    </div>

    <div class="col-12">
        <form method="get" action="<?= e(route('documenti.admin.audit')) ?>" class="card mb-0 dc-filters">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label small mb-1" for="dc-aud-q"><?= e(t('documenti.filtri.cerca_label')) ?></label>
                        <input type="text" id="dc-aud-q" name="q" class="form-control form-control-sm"
                               value="<?= e($filters['q'] ?? '') ?>" placeholder="<?= e(t('documenti.admin_audit.cerca_placeholder')) ?>">
                    </div>
                    <div class="col-6 col-sm-3 col-lg-2">
                        <label class="form-label small mb-1" for="dc-aud-ent"><?= e(t('documenti.admin_audit.entita_label')) ?></label>
                        <select id="dc-aud-ent" name="entity" class="form-select form-select-sm">
                            <option value=""><?= e(t('documenti.filtri.tutte')) ?></option>
                            <?php foreach ($entities as $ent): ?>
                                <option value="<?= e($ent) ?>" <?= ($filters['entity'] ?? '') === $ent ? 'selected' : '' ?>>
                                    <?= e($ent) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-3 col-lg-2">
                        <label class="form-label small mb-1" for="dc-aud-act"><?= e(t('documenti.admin_audit.azione_label')) ?></label>
                        <select id="dc-aud-act" name="action" class="form-select form-select-sm">
                            <option value=""><?= e(t('documenti.filtri.tutte')) ?></option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?= e($act) ?>" <?= ($filters['action'] ?? '') === $act ? 'selected' : '' ?>>
                                    <?= e($act) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-3 col-lg-2">
                        <label class="form-label small mb-1" for="dc-aud-from"><?= e(t('documenti.admin_audit.dal_label')) ?></label>
                        <input type="date" id="dc-aud-from" name="date_from" class="form-control form-control-sm"
                               value="<?= e($filters['date_from'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-sm-3 col-lg-2">
                        <label class="form-label small mb-1" for="dc-aud-to"><?= e(t('documenti.admin_audit.al_label')) ?></label>
                        <input type="date" id="dc-aud-to" name="date_to" class="form-control form-control-sm"
                               value="<?= e($filters['date_to'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-lg-1 d-flex gap-1 mt-2 mt-lg-0">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1" title="<?= e(t('documenti.admin_audit.applica_filtri_tip')) ?>">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </button>
                        <a href="<?= e(route('documenti.admin.audit')) ?>" class="btn btn-outline-secondary btn-sm" title="<?= e(t('documenti.filtri.pulisci_btn')) ?>">
                            <i class="fa-solid fa-eraser" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-12">
        <?php if (empty($logs)): ?>
            <?php $view->include('Documenti/Views/partials/empty_state', [
        'icon'      => 'fa-clipboard-list',
        'titolo'    => t('documenti.admin_audit.empty_titolo'),
        'messaggio' => t('documenti.admin_audit.empty_messaggio'),
    ]); ?>
        <?php else: ?>
        <div class="card">
            <div class="card-header py-2 small text-muted">
                <?= e(tc('documenti.admin_audit.voci_count', $total)) ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?= e(t('documenti.admin_audit.data_col')) ?></th>
                            <th><?= e(t('documenti.admin_audit.azione_label')) ?></th>
                            <th><?= e(t('documenti.admin_audit.entita_label')) ?></th>
                            <th><?= e(t('documenti.admin_audit.id_col')) ?></th>
                            <th><?= e(t('documenti.admin_elenco.owner_col')) ?></th>
                            <th><?= e(t('documenti.admin_audit.ip_col')) ?></th>
                            <th class="text-end" style="width:5rem"><?= e(t('documenti.scadenze.col_azioni')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <small data-bs-toggle="tooltip" title="<?= e(format_date($log['created_at'], 'long')) ?>">
                                    <?= e(UiHelper::timeAgo($log['created_at'])) ?>
                                </small>
                            </td>
                            <td><?= StatoHelper::azioneAuditBadge((string) $log['action']) ?></td>
                            <td><code class="dc-code"><?= e($log['entity']) ?></code></td>
                            <td><?= (int) $log['entity_id'] ?></td>
                            <td><small><?= e($log['user_name'] ?? ($log['user_id'] ? t('documenti.timeline.utente_fallback', ['id' => $log['user_id']]) : '—')) ?></small></td>
                            <td><small class="text-muted"><?= e($log['ip'] ?? '') ?></small></td>
                            <td class="text-end">
                                <?= UiHelper::ariaButton(t('documenti.admin_audit.dettaglio_entita'), 'fa-magnifying-glass-plus', [
                            'href'  => route('documenti.admin.audit.dettaglio', ['entity' => $log['entity'], 'id' => $log['entity_id']]),
                            'class' => 'btn btn-sm btn-outline-secondary',
                        ]) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
                <?php $view->include('partials/pagination', [
            'page'        => $page,
            'total_pages' => $pages,
            'total'       => $total,
            'routeName'   => 'documenti.admin.audit',
            'hxTarget'    => 'body',
            'filters'     => array_filter($filters, static fn ($v) => $v !== '' && $v !== null),
            'label'       => t('documenti.admin_audit.voci_label'),
        ]); ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>
<?php $view->end(); ?>
