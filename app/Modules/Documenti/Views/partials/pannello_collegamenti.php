<?php
/**
 * Partial: lista collegamenti documento.
 * Renderizza SOLO la lista (il contenitore card è fornito da chi include).
 *
 * Variabili: $collegamenti (array), $docId (int)
 */

$tipoColor = [
    'sostituisce' => 'warning',
    'allegato'    => 'info',
    'correlato'   => 'secondary',
    'riferimento' => 'primary',
    'versione_di' => 'success',
];
$tipoLabel = [
    'sostituisce' => t('documenti.collegamento_tipo.sostituisce'),
    'allegato'    => t('documenti.collegamento_tipo.allegato'),
    'correlato'   => t('documenti.collegamento_tipo.correlato'),
    'riferimento' => t('documenti.collegamento_tipo.riferimento'),
    'versione_di' => t('documenti.collegamento_tipo.versione_di'),
];
?>
<?php if (empty($collegamenti)): ?>
<div class="text-center text-muted py-3">
    <i class="fa-solid fa-link-slash fa-2x mb-2 d-block" aria-hidden="true"></i>
    <span><?= e(t('documenti.collegamenti.nessuno')) ?></span>
</div>
<?php else: ?>
<ul class="list-group list-group-flush">
    <?php foreach ($collegamenti as $c):
        $linkedId  = $c['collegato_id'] ?? $c['documento_destinazione_id'] ?? null;
        $titolo    = $c['titolo_collegato'] ?? ($linkedId ? t('documenti.collegamenti.documento_numero', ['id' => $linkedId]) : t('documenti.collegamenti.documento_collegato'));
        $tipo      = (string) ($c['tipo'] ?? '');
        $tipoCls   = $tipoColor[$tipo] ?? 'secondary';
        $tipoLab   = $tipoLabel[$tipo] ?? $tipo;
        ?>
    <li class="list-group-item d-flex justify-content-between align-items-start gap-2 flex-wrap">
        <div class="flex-grow-1 min-w-0">
            <?php if ($linkedId): ?>
                <a href="<?= e(route('documenti.show', ['id' => $linkedId])) ?>" class="fw-semibold text-decoration-none">
                    <?= e($titolo) ?>
                </a>
            <?php else: ?>
                <span class="fw-semibold"><?= e($titolo) ?></span>
            <?php endif; ?>
            <?php if ($tipo !== ''): ?>
                <span class="badge bg-<?= e($tipoCls) ?> ms-1"><?= e($tipoLab) ?></span>
            <?php endif; ?>
            <?php if (!empty($c['note'])): ?>
                <small class="d-block text-muted mt-1"><?= e($c['note']) ?></small>
            <?php endif; ?>
        </div>
        <?php if (has_permission('documenti.manage_collegamenti')): ?>
            <form method="post"
                  action="<?= e(route('documenti.collegamenti.destroy', ['id' => $docId, 'lid' => $c['id']])) ?>"
                  class="flex-shrink-0"
                  data-app-confirm="<?= e(t('documenti.collegamenti.rimuovi_confirm')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit"
                        class="btn btn-sm btn-outline-danger"
                        aria-label="<?= e(t('documenti.collegamenti.rimuovi_tip')) ?>"
                        title="<?= e(t('documenti.collegamenti.rimuovi_tip')) ?>"
                        data-bs-toggle="tooltip" data-bs-placement="top">
                    <i class="fa-solid fa-unlink" aria-hidden="true"></i>
                </button>
            </form>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
