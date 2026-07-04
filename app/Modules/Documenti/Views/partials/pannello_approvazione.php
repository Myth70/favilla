<?php
/**
 * Partial: pannello approvazione documento.
 * Variabili: $doc (array), $approvazioni (array), $user (array)
 */
use App\Modules\Documenti\Helpers\StatoHelper;

$stato   = $doc['stato'] ?? '';
$userId  = (int) ($user['id'] ?? 0);
$isOwner = (int) ($doc['owner_user_id'] ?? 0) === $userId;
$isAdmin = has_permission('documenti.admin');

$hasFile           = !empty($doc['versione_corrente_id']);
$approvNonRichiesta = (int) ($doc['approvazione_richiesta'] ?? 1) === 0;
$responsabile      = StatoHelper::responsabile((string) $stato);

$btnAttrs = 'hx-disabled-elt="this" hx-indicator="#dc-approv-spinner"';
?>
<div class="card mb-3 border-primary" id="dc-approvazione-card">
    <div class="card-header bg-primary bg-opacity-10 d-flex align-items-center gap-2">
        <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
        <span class="flex-grow-1"><?= e(t('documenti.approvazione.titolo')) ?></span>
        <span class="dc-spinner" id="dc-approv-spinner" role="status" aria-live="polite">
            <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
            <span class="visually-hidden"><?= e(t('documenti.approvazione.in_elaborazione')) ?></span>
        </span>
    </div>
    <div class="card-body">

        <?php $view->include('Documenti/Views/partials/workflow_stepper', ['doc' => $doc]); ?>

        <?php if ($responsabile !== null): ?>
        <p class="small text-muted text-center mb-3">
            <i class="fa-solid fa-user-clock me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.in_carico_a')) ?> <strong><?= e($responsabile) ?></strong>
        </p>
        <?php endif; ?>

        <div class="dc-approval-actions mb-3">
            <?php if ($isOwner && $stato === 'bozza' && has_permission('documenti.redazione')): ?>
                <?php if (!$hasFile): ?>
                <div class="alert alert-warning py-2 small mb-0" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i>
                    <?= e(t('documenti.approvazione.carica_file_prima')) ?>
                </div>
                <button class="btn btn-info btn-sm w-100 mt-2" disabled aria-disabled="true">
                    <i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.invia_btn')) ?>
                </button>
                <?php elseif ($approvNonRichiesta): ?>
                <form method="post" action="<?= e(route('documenti.approvazioni.invia', ['id' => $doc['id']])) ?>"
                      data-app-confirm="<?= e(t('documenti.approvazione.pubblica_diretta_confirm')) ?>">
                    <?= csrf_field() ?>
                    <p class="small text-muted mb-2"><?= e(t('documenti.approvazione.categoria_senza_approvazione')) ?></p>
                    <button class="btn btn-success btn-sm w-100" <?= $btnAttrs ?>>
                        <i class="fa-solid fa-globe me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.pubblica_diretta_btn')) ?>
                    </button>
                </form>
                <?php else: ?>
                <form method="post" action="<?= e(route('documenti.approvazioni.invia', ['id' => $doc['id']])) ?>">
                    <?= csrf_field() ?>
                    <p class="small text-muted mb-2"><?= e(t('documenti.approvazione.invia_al_controllo')) ?></p>
                    <button class="btn btn-info btn-sm w-100" <?= $btnAttrs ?>>
                        <i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.invia_btn')) ?>
                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($isOwner && $stato === 'inviato' && has_permission('documenti.redazione')): ?>
            <form method="post" action="<?= e(route('documenti.approvazioni.ritira', ['id' => $doc['id']])) ?>"
                  data-app-confirm="<?= e(t('documenti.approvazione.ritira_confirm')) ?>">
                <?= csrf_field() ?>
                <p class="small text-muted mb-2"><?= e(t('documenti.approvazione.ritira_help')) ?></p>
                <button class="btn btn-outline-secondary btn-sm w-100" <?= $btnAttrs ?>>
                    <i class="fa-solid fa-arrow-rotate-left me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.ritira_btn')) ?>
                </button>
            </form>
            <?php endif; ?>

            <?php if (has_permission('documenti.controllo') && $stato === 'inviato'): ?>
            <form method="post" action="<?= e(route('documenti.approvazioni.prende_in_carico', ['id' => $doc['id']])) ?>">
                <?= csrf_field() ?>
                <p class="small text-muted mb-2"><?= e(t('documenti.approvazione.prendi_in_carico_help')) ?></p>
                <button class="btn btn-warning btn-sm w-100" <?= $btnAttrs ?>>
                    <i class="fa-solid fa-hand me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.prendi_in_carico_btn')) ?>
                </button>
            </form>
            <?php endif; ?>

            <?php
            $canApprove = (has_permission('documenti.controllo')   && $stato === 'in_controllo')
                       || (has_permission('documenti.approvazione') && in_array($stato, ['controllato', 'in_approvazione'], true));
?>
            <?php if ($canApprove): ?>
            <form method="post" action="<?= e(route('documenti.approvazioni.approva', ['id' => $doc['id']])) ?>">
                <?= csrf_field() ?>
                <label class="form-label small mb-1" for="dc-note-approva"><?= e(t('documenti.approvazione.approva_label')) ?></label>
                <textarea id="dc-note-approva" name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="<?= e(t('documenti.approvazione.note_placeholder')) ?>"></textarea>
                <button class="btn btn-success btn-sm w-100" <?= $btnAttrs ?>>
                    <i class="fa-solid fa-check me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.approva_btn')) ?>
                </button>
            </form>

            <form method="post" action="<?= e(route('documenti.approvazioni.rifiuta', ['id' => $doc['id']])) ?>">
                <?= csrf_field() ?>
                <label class="form-label small mb-1" for="dc-note-rifiuta"><?= e(t('documenti.approvazione.rifiuta_label')) ?> <span class="text-danger">*</span></label>
                <textarea id="dc-note-rifiuta" name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="<?= e(t('documenti.approvazione.motivo_placeholder')) ?>" required></textarea>
                <button class="btn btn-danger btn-sm w-100" <?= $btnAttrs ?>>
                    <i class="fa-solid fa-xmark me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.rifiuta_btn')) ?>
                </button>
            </form>

            <form method="post" action="<?= e(route('documenti.approvazioni.restituisci', ['id' => $doc['id']])) ?>">
                <?= csrf_field() ?>
                <label class="form-label small mb-1" for="dc-note-restituisci"><?= e(t('documenti.approvazione.restituisci_label')) ?></label>
                <textarea id="dc-note-restituisci" name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="<?= e(t('documenti.approvazione.note_opzionale_placeholder')) ?>"></textarea>
                <button class="btn btn-outline-warning btn-sm w-100" <?= $btnAttrs ?>>
                    <i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.restituisci_btn')) ?>
                </button>
            </form>
            <?php endif; ?>

            <?php if ($stato === 'approvato' && $isAdmin): ?>
            <form method="post" action="<?= e(route('documenti.approvazioni.pubblica', ['id' => $doc['id']])) ?>"
                  data-app-confirm="<?= e(t('documenti.approvazione.pubblica_confirm')) ?>">
                <?= csrf_field() ?>
                <p class="small text-muted mb-2"><?= e(t('documenti.approvazione.pubblica_help')) ?></p>
                <button class="btn btn-success btn-sm w-100" <?= $btnAttrs ?>>
                    <i class="fa-solid fa-globe me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.pubblica_btn')) ?>
                </button>
            </form>
            <?php endif; ?>

            <?php if ($isAdmin && in_array($stato, ['pubblicato', 'scaduto'], true)): ?>
            <form method="post" action="<?= e(route('documenti.approvazioni.archivia', ['id' => $doc['id']])) ?>"
                  data-app-confirm="<?= e(t('documenti.approvazione.archivia_confirm')) ?>">
                <?= csrf_field() ?>
                <p class="small text-muted mb-2"><?= e(t('documenti.approvazione.archivia_help')) ?></p>
                <button class="btn btn-outline-dark btn-sm w-100" <?= $btnAttrs ?>>
                    <i class="fa-solid fa-box-archive me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.archivia_btn')) ?>
                </button>
            </form>
            <?php endif; ?>

            <?php if ($isOwner && $stato === 'rifiutato' && has_permission('documenti.redazione')): ?>
            <form method="post" action="<?= e(route('documenti.approvazioni.riprendi', ['id' => $doc['id']])) ?>">
                <?= csrf_field() ?>
                <p class="small text-muted mb-2"><?= e(t('documenti.approvazione.riprendi_help')) ?></p>
                <button class="btn btn-outline-secondary btn-sm w-100" <?= $btnAttrs ?>>
                    <i class="fa-solid fa-rotate-right me-1" aria-hidden="true"></i><?= e(t('documenti.approvazione.riprendi_btn')) ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($approvazioni)): ?>
        <hr class="my-3">
        <h6 class="text-muted small mb-2"><?= e(t('documenti.approvazione.storico_titolo')) ?></h6>
        <ol class="list-group list-group-flush">
            <?php foreach ($approvazioni as $ap):
                $userLabel = $ap['user_name'] ?? '';
                if ($userLabel === '' || $userLabel === null) {
                    $userLabel = t('documenti.timeline.utente_fallback', ['id' => (int) ($ap['user_id'] ?? 0)]);
                }
                $note = (string) ($ap['note'] ?? '');
                $noteLong = mb_strlen($note) > 80;
                ?>
            <li class="list-group-item px-2 py-2">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div class="flex-grow-1 min-w-0">
                        <?= StatoHelper::azioneAuditBadge((string) $ap['azione']) ?>
                        <span class="text-muted small ms-2"><?= e($userLabel) ?></span>
                        <?php if ($note !== ''): ?>
                            <p class="mb-0 small fst-italic <?= $noteLong ? 'text-truncate' : '' ?>"
                               <?= $noteLong ? 'data-bs-toggle="tooltip" title="' . e($note) . '"' : '' ?>>
                                <?= e($note) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted flex-shrink-0"
                           data-bs-toggle="tooltip"
                           title="<?= e(format_date($ap['created_at'], 'long')) ?>">
                        <?= e(format_date($ap['created_at'], 'compact')) ?>
                    </small>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </div>
</div>
