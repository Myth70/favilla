<?php
/**
 * Partial: timeline versioni documento.
 * Variabili: $versioni (array di documenti_versioni JOIN documenti_files),
 *            $docId (int), $versioneCorrenteId (int|null)
 */
use App\Modules\Documenti\Helpers\UiHelper;

$versioneCorrenteId = isset($versioneCorrenteId) ? (int) $versioneCorrenteId : null;
?>
<?php if (empty($versioni)): ?>
<div class="text-muted text-center py-3">
    <i class="fa-solid fa-file-circle-question mb-2 d-block fa-2x" aria-hidden="true"></i>
    <?= e(t('documenti.timeline.nessuna_versione')) ?>
</div>
<?php else: ?>
<ol class="dc-timeline list-unstyled">
    <?php foreach ($versioni as $v):
        $isCorrente = $versioneCorrenteId !== null && (int) $v['id'] === $versioneCorrenteId;
        $statoVer   = (string) ($v['stato'] ?? '');
        $size       = (int) ($v['file_size'] ?? $v['size'] ?? 0);
        $mime       = (string) ($v['mime_type'] ?? $v['file_mime'] ?? '');
        $authorName = (string) ($v['author_name'] ?? $v['created_by_name'] ?? '');
        $authorId   = (int) ($v['created_by'] ?? 0);
        ?>
    <li class="dc-timeline-item <?= $isCorrente ? 'dc-timeline-current' : '' ?>">
        <div class="dc-timeline-marker" aria-hidden="true"></div>
        <div class="dc-timeline-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="fw-semibold">v<?= e($v['versione_no']) ?></span>
                        <?php if ($isCorrente): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-check me-1" aria-hidden="true"></i><?= e(t('documenti.timeline.corrente')) ?></span>
                        <?php endif; ?>
                        <?php if ($statoVer === 'sostituito'): ?>
                            <span class="badge bg-secondary"><?= e(t('documenti.timeline.sostituita')) ?></span>
                        <?php elseif ($statoVer === 'pubblicato'): ?>
                            <span class="badge bg-primary"><?= e(t('documenti.timeline.pubblicata')) ?></span>
                        <?php elseif ($statoVer === 'bozza'): ?>
                            <span class="badge bg-light text-secondary"><?= e(t('documenti.stato.bozza')) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($v['note_modifica'])): ?>
                        <p class="mb-1 small"><?= e($v['note_modifica']) ?></p>
                    <?php endif; ?>
                    <div class="small text-muted">
                        <span data-bs-toggle="tooltip" title="<?= e(format_date($v['created_at'], 'long')) ?>">
                            <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i><?= e(format_date($v['created_at'], 'compact')) ?>
                        </span>
                        <?php if ($size > 0): ?>
                            <span class="ms-2">
                                <i class="fa-solid fa-database me-1" aria-hidden="true"></i><?= e(UiHelper::formatBytes($size)) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($mime !== ''): ?>
                            <span class="ms-2"><i class="fa-solid fa-file-lines me-1" aria-hidden="true"></i><?= e($mime) ?></span>
                        <?php endif; ?>
                        <?php if ($authorName !== '' || $authorId > 0): ?>
                            <span class="ms-2">
                                <i class="fa-solid fa-user me-1" aria-hidden="true"></i>
                                <?= e($authorName !== '' ? $authorName : t('documenti.timeline.utente_fallback', ['id' => $authorId])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-1 flex-shrink-0">
                    <?= UiHelper::ariaButton(t('documenti.timeline.scarica_versione', ['no' => $v['versione_no']]), 'fa-download', [
                            'href'  => route('documenti.versioni.download', ['id' => $docId, 'vid' => $v['id']]),
                            'class' => 'btn btn-sm btn-outline-secondary',
                        ]) ?>
                    <?php if (!$isCorrente && has_permission('documenti.redazione')): ?>
                        <form method="post"
                            action="<?= e(route('documenti.versioni.ripristina', ['id' => $docId, 'vid' => $v['id']])) ?>"
                            class="d-inline"
                            data-app-confirm="<?= e(t('documenti.timeline.ripristina_confirm', ['no' => $v['versione_no']])) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-warning"
                                    type="submit"
                                    aria-label="<?= e(t('documenti.timeline.ripristina_versione', ['no' => $v['versione_no']])) ?>"
                                    title="<?= e(t('documenti.timeline.ripristina_tip')) ?>"
                                    data-bs-toggle="tooltip" data-bs-placement="top">
                                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </li>
    <?php endforeach; ?>
</ol>
<?php endif; ?>
