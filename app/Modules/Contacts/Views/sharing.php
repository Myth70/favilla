<?php
/**
 * Variabili: $item, $nomeCompleto, $roles, $sharedRoleIds
 */
$view->layout('main');
$view->pushStyle('css/contacts.css');

$selected = array_flip(array_map('intval', $sharedRoleIds));
?>
<?php $view->start('content'); ?>

<?php
$moduleButtonsHtml  = '<a href="' . e(route('contacts.show', ['id' => $item['id']])) . '"';
$moduleButtonsHtml .= ' class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('contacts.sharing.cancel_tip')) . '">';
$moduleButtonsHtml .= '<i class="fa-solid fa-xmark"></i> ' . e(t('common.action.cancel')) . '</a>';

$view->include('partials/pf-hero-module', [
    'moduleName'     => e(t('contacts.show.share_tip')) . ': ' . e($nomeCompleto),
    'moduleIcon'     => 'fa-solid fa-users',
    'moduleSubtitle' => e(t('contacts.sharing.page_subtitle')),
    'moduleButtons'  => $moduleButtonsHtml,
]);
?>

<div class="container-fluid">
<div class="row justify-content-center">
<div class="col-xl-8 col-lg-10">

  <form method="POST" action="<?= e(route('contacts.sharing.update', ['id' => $item['id']])) ?>" novalidate data-app-form>
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex align-items-center gap-2 py-2 px-3">
        <span class="app-card-icon"><i class="fa-solid fa-user-tag"></i></span>
        <span class="fw-semibold"><?= e(t('contacts.sharing.recipients_section')) ?></span>
        <small class="text-muted ms-auto"><?= e(t('contacts.sharing.readonly_note')) ?></small>
      </div>
      <div class="card-body p-3">
        <?php if (empty($roles)): ?>
          <div class="text-muted small text-center py-3">
            <i class="fa-regular fa-circle-question me-1 opacity-50"></i>
            <?= e(t('contacts.sharing.no_roles')) ?>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-3">
            <?= t('contacts.sharing.privacy_intro') ?>
          </p>

          <div class="row g-2">
            <?php foreach ($roles as $role):
              $rid = (int) $role['id'];
              $checked = isset($selected[$rid]);
            ?>
            <div class="col-md-6">
              <label class="form-check d-flex align-items-start gap-2 p-2 border rounded">
                <input class="form-check-input mt-1" type="checkbox" name="roles[]"
                       value="<?= $rid ?>" <?= $checked ? 'checked' : '' ?>>
                <span>
                  <span class="fw-semibold d-block"><?= e($role['name']) ?></span>
                  <?php if (!empty($role['description'])): ?>
                  <span class="small text-muted d-block"><?= e($role['description']) ?></span>
                  <?php endif; ?>
                  <span class="small text-muted">slug: <code><?= e($role['slug']) ?></code></span>
                </span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3 mb-4">
      <a href="<?= e(route('contacts.show', ['id' => $item['id']])) ?>" class="btn btn-outline-secondary"><?= e(t('common.action.cancel')) ?></a>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-check" aria-hidden="true"></i> <?= e(t('contacts.sharing.submit_btn')) ?>
      </button>
    </div>
  </form>

</div>
</div>
</div>

<?php $view->end(); ?>
