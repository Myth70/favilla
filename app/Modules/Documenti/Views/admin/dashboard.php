<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php
use App\Modules\Documenti\Helpers\StatoHelper;

?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php $view->include('partials/pf-hero-module', [
            'moduleName'     => t('documenti.admin_dashboard.hero_title'),
            'moduleIcon'     => 'fa-solid fa-file-shield',
            'moduleSubtitle' => t('documenti.admin_dashboard.subtitle'),
            'moduleButtons'  => '',
        ]); ?>
    </div>

    <div class="col-12">
        <h5 class="mb-3"><i class="fa-solid fa-chart-pie me-2" aria-hidden="true"></i><?= e(t('documenti.admin_dashboard.per_stato_titolo')) ?></h5>
        <div class="row g-3">
            <?php foreach (StatoHelper::STATI as $stato => $info):
                $cnt = (int) ($stats[$stato] ?? 0);
                if ($stato === 'archiviato' && $cnt === 0) {
                    continue;
                }
                $label = StatoHelper::label($stato);
                ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <a href="<?= e(route('documenti.admin.elenco')) ?>?stato[]=<?= e($stato) ?>" class="text-decoration-none">
                    <div class="card h-100 border-<?= e($info['color']) ?> text-center"
                         data-bs-toggle="tooltip"
                         title="<?= e(t('documenti.admin_dashboard.filtra_stato_tip', ['stato' => $label])) ?>">
                        <div class="card-body py-3">
                            <div class="display-6 fw-bold text-<?= e($info['color']) ?>"><?= $cnt ?></div>
                            <div class="small">
                                <i class="fa-solid <?= e($info['icon']) ?> me-1 text-<?= e($info['color']) ?>" aria-hidden="true"></i>
                                <?= e($label) ?>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-12">
        <h5 class="mb-3"><i class="fa-solid fa-bolt me-2" aria-hidden="true"></i><?= e(t('documenti.admin_dashboard.azioni_rapide')) ?></h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(route('documenti.admin.elenco')) ?>" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-list me-1" aria-hidden="true"></i><?= e(t('documenti.admin.elenco_title')) ?>
            </a>
            <a href="<?= e(route('documenti.admin.trash')) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-trash me-1" aria-hidden="true"></i><?= e(t('documenti.admin.trash_title')) ?>
            </a>
            <a href="<?= e(route('documenti.admin.audit')) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-scroll me-1" aria-hidden="true"></i><?= e(t('documenti.admin_dashboard.audit_log_btn')) ?>
            </a>
            <a href="<?= e(route('documenti.admin.health')) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-heart-pulse me-1" aria-hidden="true"></i><?= e(t('documenti.admin.health_title')) ?>
            </a>
            <a href="<?= e(route('documenti.admin.mime')) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-file-code me-1" aria-hidden="true"></i><?= e(t('documenti.admin_dashboard.tipi_mime_btn')) ?>
            </a>
            <a href="<?= e(route('documenti.admin.sequenze')) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-hashtag me-1" aria-hidden="true"></i><?= e(t('documenti.admin.sequenze_title')) ?>
            </a>
        </div>
    </div>

    <div class="col-12">
        <h5 class="mb-3"><i class="fa-solid fa-robot me-2" aria-hidden="true"></i><?= e(t('documenti.admin_dashboard.job_manuali')) ?></h5>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fa-solid fa-bell text-info me-1" aria-hidden="true"></i><?= e(t('documenti.admin_dashboard.invia_reminder_titolo')) ?>
                        </h6>
                        <p class="text-muted small">
                            <?= e(t('documenti.admin_dashboard.invia_reminder_descrizione')) ?>
                        </p>
                        <form method="post" action="<?= e(route('documenti.admin.jobs.reminders')) ?>"
                              data-app-confirm="<?= e(t('documenti.admin_dashboard.invia_reminder_confirm')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-info btn-sm" hx-disabled-elt="this">
                                <i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i><?= e(t('documenti.admin_dashboard.esegui_adesso')) ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fa-solid fa-hourglass-end text-warning me-1" aria-hidden="true"></i><?= e(t('documenti.admin_dashboard.scadenza_pubblicati_titolo')) ?>
                        </h6>
                        <p class="text-muted small">
                            <?= e(t('documenti.admin_dashboard.scadenza_pubblicati_descrizione')) ?>
                        </p>
                        <form method="post" action="<?= e(route('documenti.admin.jobs.expire')) ?>"
                              data-app-confirm="<?= e(t('documenti.admin_dashboard.scadenza_pubblicati_confirm')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-warning btn-sm" hx-disabled-elt="this">
                                <i class="fa-solid fa-play me-1" aria-hidden="true"></i><?= e(t('documenti.admin_dashboard.esegui_adesso')) ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</div>
<?php $view->end(); ?>
