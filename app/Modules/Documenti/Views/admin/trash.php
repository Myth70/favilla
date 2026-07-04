<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php
use App\Modules\Documenti\Helpers\UiHelper;

$items = $items ?? [];
?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php
        $heroButtons = '';
if (!empty($items)) {
    $heroButtons = '<span class="dc-hero-pill"><i class="fa-solid fa-trash-can me-1" aria-hidden="true"></i>'
                 . e(t('documenti.admin_trash.nel_cestino_pill', ['count' => count($items)])) . '</span>';
}
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('documenti.admin_trash.hero_title'),
    'moduleIcon'     => 'fa-solid fa-trash-can',
    'moduleSubtitle' => t('documenti.admin_trash.subtitle'),
    'moduleButtons'  => $heroButtons,
]); ?>
    </div>

    <div class="col-12">
        <?php if (empty($items)): ?>
            <?php $view->include('Documenti/Views/partials/empty_state', [
        'icon'      => 'fa-check-circle',
        'titolo'    => t('documenti.admin_trash.empty_titolo'),
        'messaggio' => t('documenti.admin_trash.empty_messaggio'),
    ]); ?>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?= e(t('documenti.widget.col_documento')) ?></th>
                            <th><?= e(t('documenti.admin_elenco.owner_col')) ?></th>
                            <th><?= e(t('documenti.admin_trash.eliminato_col')) ?></th>
                            <th class="text-end" style="width:10rem"><?= e(t('documenti.scadenze.col_azioni')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr class="dc-row-deleted">
                            <td>
                                <span class="fw-semibold dc-deleted-strike"><?= e($item['titolo']) ?></span>
                                <?php if (!empty($item['protocollo'])): ?>
                                    <small class="d-block"><code class="dc-code"><?= e($item['protocollo']) ?></code></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?= e($item['owner_name'] ?? t('documenti.timeline.utente_fallback', ['id' => (int) ($item['owner_user_id'] ?? 0)])) ?></small></td>
                            <td>
                                <small data-bs-toggle="tooltip" title="<?= e(format_date($item['deleted_at'], 'long')) ?>">
                                    <?= e(UiHelper::timeAgo($item['deleted_at'])) ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <form method="post"
                                      action="<?= e(route('documenti.admin.trash.restore', ['id' => $item['id']])) ?>"
                                      class="d-inline"
                                      data-app-confirm="<?= e(t('documenti.admin_trash.ripristina_confirm', ['titolo' => $item['titolo']])) ?>">
                                    <?= csrf_field() ?>
                                    <?= UiHelper::ariaButton(t('documenti.admin_trash.ripristina_documento'), 'fa-trash-can-arrow-up', [
                                'type'  => 'submit',
                                'class' => 'btn btn-sm btn-outline-success',
                            ]) ?>
                                </form>
                                <form method="post"
                                      action="<?= e(route('documenti.admin.trash.purge', ['id' => $item['id']])) ?>"
                                      class="d-inline"
                                      data-app-confirm="<?= e(t('documenti.admin_trash.purge_confirm', ['titolo' => $item['titolo']])) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <?= UiHelper::ariaButton(t('documenti.admin_trash.elimina_definitivamente'), 'fa-bomb', [
                                'type'  => 'submit',
                                'class' => 'btn btn-sm btn-danger',
                            ]) ?>
                                </form>
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
