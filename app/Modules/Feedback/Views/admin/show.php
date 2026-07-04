<?php $view->layout('main'); ?>
<?php $view->start('content'); ?>
<?php
use App\Modules\Feedback\Services\FeedbackService;

$tipiMeta     = FeedbackService::tipiMeta();
$severitaMeta = FeedbackService::severitaMeta();
$statiMeta    = FeedbackService::statiMeta();

$tipo = $tipiMeta[$item['tipo']] ?? ['label' => $item['tipo'], 'color' => 'secondary', 'icon' => 'fa-circle'];
$sev  = $severitaMeta[$item['severita']] ?? ['label' => $item['severita'], 'color' => 'secondary'];
$stt  = $statiMeta[$item['stato']] ?? ['label' => $item['stato'], 'color' => 'secondary'];

$contesto   = json_decode((string) ($item['contesto_json'] ?? ''), true) ?: [];
$client     = is_array($contesto['client'] ?? null) ? $contesto['client'] : [];
$server     = is_array($contesto['server'] ?? null) ? $contesto['server'] : [];
$errori     = json_decode((string) ($item['errori_console_json'] ?? ''), true);
if (!is_array($errori)) {
    $errori = is_array($client['errors'] ?? null) ? $client['errors'] : [];
}
$breadcrumb = is_array($client['breadcrumb'] ?? null) ? $client['breadcrumb'] : [];
$canManage  = has_permission('feedback.manage');

$adminButtons = '<button type="button" class="btn btn-sm btn-primary" data-sg-copy="#sg-llm-source">'
    . '<i class="fa-solid fa-robot me-1"></i>' . e(t('feedback.detail.copy_llm')) . '</button>'
    . '<a href="' . e(route('feedback.admin.export', ['id' => (int) $item['id']]) . '?format=md') . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-download me-1"></i>.md</a>'
    . '<a href="' . e(route('feedback.admin.export', ['id' => (int) $item['id']]) . '?format=json') . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-download me-1"></i>.json</a>'
    . (!empty($item['dom_snapshot'])
        ? '<a href="' . e(route('feedback.admin.dom', ['id' => (int) $item['id']])) . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-code me-1"></i>DOM</a>'
        : '')
    . '<a href="' . e(route('feedback.admin.index')) . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-list me-1"></i>' . e(t('feedback.detail.list')) . '</a>';

// Riga di ambiente: etichetta => valore (solo non vuoti)
$envRows = [
    t('feedback.env.autore')      => ($item['creatore_nome'] ?? '') . (!empty($item['creatore_email']) ? ' (' . $item['creatore_email'] . ')' : ''),
    t('feedback.env.ruoli')       => isset($server['user']['roles']) ? implode(', ', (array) $server['user']['roles']) : '',
    t('feedback.env.data')        => format_date($item['created_at'] ?? null, 'long'),
    t('feedback.env.app_version') => $item['app_version'] ?? ($server['app_version'] ?? ''),
    t('feedback.env.php')         => $server['php_version'] ?? '',
    t('feedback.env.ip')          => $server['ip'] ?? '',
    t('feedback.env.modulo')      => $item['modulo'] ?? ($server['modulo'] ?? ''),
    t('feedback.env.route')       => $item['route_name'] ?? '',
    t('feedback.env.viewport')    => $item['viewport'] ?? '',
    t('feedback.env.lingua')      => $client['language'] ?? '',
    t('feedback.env.user_agent')  => $item['user_agent'] ?? ($client['user_agent'] ?? ''),
];
?>

<textarea hidden id="sg-llm-source"><?= e($markdown ?? '') ?></textarea>

<div class="container-fluid app-page-wide">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid ' . ($tipo['icon'] ?? 'fa-bug'),
        'adminTitle'    => ($item['ref_code'] ?? ('#' . $item['id'])) . ' — ' . ($item['titolo'] ?? ''),
        'adminSubtitle' => t('feedback.detail.subtitle', ['type' => e($tipo['label']), 'status' => e($stt['label'])]),
        'adminButtons'  => $adminButtons,
    ]); ?>

    <?php if (!empty($item['pagina_url'])): ?>
        <div class="mb-3">
            <a href="<?= e((string) $item['pagina_url']) ?>" class="small text-decoration-none" target="_blank" rel="noopener">
                <i class="fa-solid fa-up-right-from-square me-1"></i><?= e((string) $item['pagina_url']) ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Colonna principale -->
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-transparent d-flex align-items-center gap-2">
                    <span class="badge text-bg-<?= e($tipo['color']) ?>"><i class="fa-solid <?= e($tipo['icon']) ?> me-1"></i><?= e($tipo['label']) ?></span>
                    <span class="badge text-bg-<?= e($sev['color']) ?>"><?= e(t('feedback.detail.severity_prefix')) ?> <?= e($sev['label']) ?></span>
                    <span class="badge text-bg-<?= e($stt['color']) ?>"><?= e($stt['label']) ?></span>
                </div>
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase"><?= e(t('feedback.detail.description')) ?></h6>
                    <p class="mb-3" style="white-space: pre-wrap;"><?= e((string) ($item['descrizione'] ?? '')) ?></p>

                    <?php if (!empty($item['passi'])): ?>
                        <h6 class="text-muted small text-uppercase"><?= e(t('feedback.detail.steps')) ?></h6>
                        <p class="mb-0" style="white-space: pre-wrap;"><?= e((string) $item['passi']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i><?= e(t('feedback.detail.captured_errors')) ?>
                    <span class="badge text-bg-secondary ms-1"><?= count($errori) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($errori)): ?>
                        <p class="text-muted small mb-0"><?= e(t('feedback.detail.no_errors')) ?></p>
                    <?php else: ?>
                        <pre class="sg-pre"><?php
                        foreach ($errori as $err) {
                            $err = (array) $err;
                            if (($err['type'] ?? '') === 'htmx') {
                                echo e(sprintf("[HTMX] %s %s → HTTP %s  (%s)\n",
                                    strtoupper((string) ($err['verb'] ?? '')),
                                    (string) ($err['path'] ?? ''),
                                    (string) ($err['status'] ?? '?'),
                                    (string) ($err['ts'] ?? '')));
                            } else {
                                echo e(sprintf("[JS] %s  (%s:%s)  %s\n",
                                    (string) ($err['message'] ?? ''),
                                    (string) ($err['source'] ?? ''),
                                    (string) ($err['line'] ?? ''),
                                    (string) ($err['ts'] ?? '')));
                                if (!empty($err['stack'])) {
                                    echo e('     ' . str_replace("\n", "\n     ", trim((string) $err['stack'])) . "\n");
                                }
                            }
                        }
                        ?></pre>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="fa-solid fa-route me-1 text-primary"></i><?= e(t('feedback.detail.action_sequence')) ?>
                </div>
                <div class="card-body">
                    <?php if (empty($breadcrumb)): ?>
                        <p class="text-muted small mb-0"><?= e(t('feedback.detail.no_interactions')) ?></p>
                    <?php else: ?>
                        <ul class="sg-timeline">
                            <?php foreach ($breadcrumb as $b): $b = (array) $b; ?>
                                <li>
                                    <span class="sg-crumb-ts"><?= e((string) ($b['ts'] ?? '')) ?></span>
                                    <?php
                                    $kind = (string) ($b['kind'] ?? '');
                                    if ($kind === 'htmx') {
                                        echo ' HTMX <code>' . e(strtoupper((string) ($b['verb'] ?? '')) . ' ' . (string) ($b['path'] ?? '')) . '</code> → ' . e((string) ($b['status'] ?? '?'));
                                    } elseif ($kind === 'nav') {
                                        echo ' ' . e(t('feedback.detail.crumb_nav')) . ' <code>' . e((string) ($b['path'] ?? '')) . '</code>';
                                    } elseif ($kind === 'click') {
                                        echo ' ' . e(t('feedback.detail.crumb_click')) . ' <code>' . e((string) ($b['target'] ?? '')) . '</code>';
                                    } else {
                                        echo ' ' . e((string) ($b['kind'] ?? ''));
                                    }
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($item['dom_snapshot'])): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <div class="fw-semibold"><i class="fa-solid fa-code me-1"></i><?= e(t('feedback.detail.dom_available')) ?></div>
                        <div class="small text-muted"><?= t('feedback.detail.dom_desc') ?></div>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary text-nowrap" href="<?= e(route('feedback.admin.dom', ['id' => (int) $item['id']])) ?>">
                        <i class="fa-solid fa-download me-1"></i><?= e(t('feedback.detail.download_dom')) ?>
                    </a>
                </div>
            </div>
            <?php elseif (!in_array($item['stato'], FeedbackService::STATI_APERTI, true)): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-muted small">
                    <i class="fa-solid fa-shield-halved me-1"></i><?= e(t('feedback.detail.dom_deleted')) ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="fa-solid fa-code me-1"></i><?= e(t('feedback.detail.full_context')) ?>
                </div>
                <div class="card-body">
                    <details>
                        <summary class="small text-muted mb-2"><?= e(t('feedback.detail.show_hide_json')) ?></summary>
                        <pre class="sg-pre mt-2"><?= e(json_encode($contesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
                    </details>
                </div>
            </div>
        </div>

        <!-- Colonna laterale -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold"><i class="fa-solid fa-circle-info me-1"></i><?= e(t('feedback.detail.environment')) ?></div>
                <div class="card-body">
                    <ul class="sg-meta-list">
                        <?php foreach ($envRows as $label => $value): ?>
                            <?php if (trim((string) $value) !== ''): ?>
                                <li>
                                    <span class="sg-meta-label"><?= e((string) $label) ?></span>
                                    <span class="sg-meta-value"><?= e((string) $value) ?></span>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <?php if ($canManage): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-transparent fw-semibold"><i class="fa-solid fa-sliders me-1"></i><?= e(t('feedback.detail.management')) ?></div>
                    <div class="card-body">
                        <form method="POST" action="<?= e(route('feedback.admin.triage', ['id' => (int) $item['id']])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_method" value="PUT">

                            <div class="mb-2">
                                <label class="form-label small text-muted" for="sg-stato"><?= e(t('common.label.status')) ?></label>
                                <select class="form-select form-select-sm" id="sg-stato" name="stato">
                                    <?php foreach ($statiMeta as $k => $m): ?>
                                        <option value="<?= e($k) ?>" <?= ($item['stato'] ?? '') === $k ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small text-muted" for="sg-sev"><?= e(t('feedback.form.severita')) ?></label>
                                <select class="form-select form-select-sm" id="sg-sev" name="severita">
                                    <?php foreach ($severitaMeta as $k => $m): ?>
                                        <option value="<?= e($k) ?>" <?= ($item['severita'] ?? '') === $k ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small text-muted" for="sg-assegnata"><?= e(t('feedback.detail.assigned_to')) ?></label>
                                <select class="form-select form-select-sm" id="sg-assegnata" name="assegnata_a">
                                    <option value="0"><?= e(t('feedback.detail.not_assigned')) ?></option>
                                    <?php foreach (($assignees ?? []) as $uid => $uname): ?>
                                        <option value="<?= (int) $uid ?>" <?= (int) ($item['assegnata_a'] ?? 0) === (int) $uid ? 'selected' : '' ?>><?= e((string) $uname) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted" for="sg-note"><?= e(t('feedback.detail.admin_notes')) ?></label>
                                <textarea class="form-control form-control-sm" id="sg-note" name="note_admin" rows="3" maxlength="5000"><?= e((string) ($item['note_admin'] ?? '')) ?></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i><?= e(t('common.action.update')) ?></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-danger-subtle">
                    <div class="card-header bg-transparent fw-semibold text-danger"><i class="fa-solid fa-trash me-1"></i><?= e(t('feedback.detail.delete')) ?></div>
                    <div class="card-body">
                        <p class="small text-muted"><?= e(t('feedback.detail.delete_desc')) ?></p>
                        <form method="POST" action="<?= e(route('feedback.admin.destroy', ['id' => (int) $item['id']])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_method" value="DELETE">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        data-app-confirm="<?= e(t('feedback.detail.delete_confirm', ['ref' => $item['ref_code']])) ?>"><?= e(t('feedback.detail.delete_btn')) ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $view->end(); ?>
