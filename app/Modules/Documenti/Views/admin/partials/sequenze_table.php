<?php
/**
 * Partial: sequenze_table — reusable table fragment for HTMX swap.
 * Variabili: $sequenze (array)
 */
?>
<?php if (empty($sequenze)): ?>
    <?php $view->include('Documenti/Views/partials/empty_state', [
        'icon'      => 'fa-hashtag',
        'titolo'    => t('documenti.admin_sequenze.empty_titolo'),
        'messaggio' => t('documenti.admin_sequenze.empty_messaggio'),
    ]); ?>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= e(t('documenti.create.categoria_label')) ?></th>
                    <th><?= e(t('documenti.admin_sequenze.codice_col')) ?></th>
                    <th><?= e(t('documenti.admin_sequenze.anno_col')) ?></th>
                    <th><?= e(t('documenti.admin_sequenze.progressivo_col')) ?></th>
                    <th class="text-end" style="width:10rem"><?= e(t('documenti.admin_sequenze.azione_col')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sequenze as $seq):
                    $anno  = (string) ($seq['anno'] ?? '');
                    $label = (string) ($seq['categoria_nome'] ?? $seq['categoria_codice'] ?? t('documenti.admin_sequenze.categoria_fallback'));
                    ?>
                <tr>
                    <td><?= e($label) ?></td>
                    <td><code class="dc-code"><?= e($seq['categoria_codice'] ?? '') ?></code></td>
                    <td><?= e($anno) ?></td>
                    <td><strong><?= str_pad((string) (int) ($seq['ultimo_numero'] ?? 0), 4, '0', STR_PAD_LEFT) ?></strong></td>
                    <td class="text-end">
                        <form method="post"
                            action="<?= e(route('documenti.admin.sequenze.reset', ['categoriaId' => $seq['categoria_id']])) ?>"
                            hx-post="<?= e(route('documenti.admin.sequenze.reset', ['categoriaId' => $seq['categoria_id']])) ?>"
                            hx-target="#sequenze-table-container"
                            hx-swap="innerHTML"
                            class="d-inline"
                            data-app-confirm="<?= e(t('documenti.admin_sequenze.reset_confirm', ['anno' => $anno, 'label' => $label])) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger" hx-disabled-elt="this">
                                <i class="fa-solid fa-arrows-rotate me-1" aria-hidden="true"></i><?= e(t('documenti.admin_sequenze.reset_btn', ['anno' => $anno])) ?>
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
