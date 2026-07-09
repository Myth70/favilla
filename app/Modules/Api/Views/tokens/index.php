<?php
$view->layout('main');
$view->start('content');
?>

<div class="container-fluid py-3">

<?php
$apiButtons = '<a href="' . e(route('profile')) . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-user"></i> ' . e(t('common.user.profile')) . '</a>';
$apiButtons .= '<a href="' . e(route('api.openapi')) . '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-book"></i> ' . e(t('api.tokens.api_docs')) . '</a>';
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('api.tokens.title'),
    'moduleIcon'     => 'fa-solid fa-plug',
    'moduleSubtitle' => t('api.tokens.subtitle'),
    'moduleButtons'  => $apiButtons,
]);
?>

    <?php if (!empty($newPlainToken)): ?>
    <div class="alert alert-success shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-2 mb-2">
            <i class="fa-solid fa-circle-check"></i>
            <strong><?= e(t('api.tokens.created_once_title')) ?></strong>
        </div>
        <p class="small mb-2"><?= e(t('api.tokens.created_once_hint')) ?></p>
        <div class="input-group">
            <input type="text" class="form-control font-monospace" id="api-new-token" value="<?= e($newPlainToken) ?>" readonly>
            <button type="button" class="btn btn-outline-secondary" onclick="(function(b){var i=document.getElementById('api-new-token');i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value);b.innerHTML='<i class=\'fa-solid fa-check\'></i>';})(this)">
                <i class="fa-solid fa-copy"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Nuovo token -->
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-key"></i></span>
                    <span class="fw-semibold"><?= e(t('api.tokens.create_title')) ?></span>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= e(route('api.tokens.store')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="api-token-name"><?= e(t('api.tokens.field_name')) ?></label>
                            <input type="text" class="form-control" id="api-token-name" name="name" maxlength="120" required
                                   placeholder="<?= e(t('api.tokens.field_name_ph')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="api-token-expires"><?= e(t('api.tokens.field_expiry')) ?></label>
                            <select class="form-select" id="api-token-expires" name="expires">
                                <option value="never"><?= e(t('api.tokens.expiry_never')) ?></option>
                                <option value="30"><?= e(t('api.tokens.expiry_30')) ?></option>
                                <option value="90"><?= e(t('api.tokens.expiry_90')) ?></option>
                                <option value="365"><?= e(t('api.tokens.expiry_365')) ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= e(t('api.tokens.field_scopes')) ?></label>
                            <p class="small text-secondary mb-2"><?= e(t('api.tokens.field_scopes_hint')) ?></p>
                            <div class="border rounded p-2" style="max-height: 260px; overflow-y: auto;">
                                <?php if (empty($availableScopes)): ?>
                                    <span class="small text-secondary"><?= e(t('api.tokens.no_scopes')) ?></span>
                                <?php else: ?>
                                    <?php foreach ($availableScopes as $scope): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="scopes[]"
                                               value="<?= e($scope) ?>" id="scope-<?= e($scope) ?>">
                                        <label class="form-check-label font-monospace small" for="scope-<?= e($scope) ?>"><?= e($scope) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-plus me-1"></i><?= e(t('api.tokens.create_submit')) ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Token esistenti -->
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-list"></i></span>
                    <span class="fw-semibold"><?= e(t('api.tokens.list_title')) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($tokens)): ?>
                        <div class="p-4 text-center text-secondary"><?= e(t('api.tokens.empty')) ?></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?= e(t('api.tokens.col_name')) ?></th>
                                    <th><?= e(t('api.tokens.col_scopes')) ?></th>
                                    <th><?= e(t('api.tokens.col_expires')) ?></th>
                                    <th><?= e(t('api.tokens.col_last_used')) ?></th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tokens as $token): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($token['name']) ?></td>
                                    <td>
                                        <?php if ($token['scopes'] === null): ?>
                                            <span class="badge text-bg-secondary"><?= e(t('api.tokens.scope_full')) ?></span>
                                        <?php else: ?>
                                            <span class="badge text-bg-info"><?= (int) count($token['scopes']) ?> scope</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= $token['expires_at'] ? e(format_date_it($token['expires_at'])) : '<span class="text-secondary">' . e(t('api.tokens.expiry_never')) . '</span>' ?></td>
                                    <td class="small"><?= $token['last_used_at'] ? e(format_date_it($token['last_used_at'])) : '<span class="text-secondary">—</span>' ?></td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= e(route('api.tokens.revoke', ['id' => $token['id']])) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    data-app-confirm="<?= e(t('api.tokens.revoke_confirm')) ?>"
                                                    data-app-confirm-label="<?= e(t('api.tokens.revoke')) ?>">
                                                <i class="fa-solid fa-ban me-1"></i><?= e(t('api.tokens.revoke')) ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php $view->end(); ?>
