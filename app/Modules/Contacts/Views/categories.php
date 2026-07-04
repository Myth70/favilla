<?php
$view->layout('main');
$view->pushStyle('css/contacts.css');
$view->pushScript('js/contacts.js');

$categorieButtons = '<a href="' . e(route('contacts.index')) . '" class="btn btn-sm btn-outline-secondary">'
    . '<i class="fa-solid fa-arrow-left me-1"></i>' . e(t('contacts.categories.back_btn')) . '</a>';
?>
<?php $view->start('content'); ?>

<div class="container-fluid">
<?php $view->include('partials/pf-hero-module', [
    'moduleName'     => t('contacts.categories.page_title'),
    'moduleIcon'     => 'fa-solid fa-tags',
    'moduleSubtitle' => e(t('contacts.categories.page_subtitle')),
    'moduleButtons'  => $categorieButtons,
]); ?>
<div class="row justify-content-center">
<div class="col-lg-7">

  <!-- Form nuova categoria -->
  <div class="card mb-3">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="app-card-icon"><i class="fa-solid fa-plus"></i></span>
      <span class="fw-semibold"><?= e(t('contacts.categories.section_new')) ?></span>
    </div>
    <div class="card-body p-3">
      <form hx-post="<?= e(route('contacts.categories.store')) ?>"
            hx-target="#ct-cat-list" hx-swap="innerHTML"
            hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'>
        <?= csrf_field() ?>
        <div class="d-flex gap-2 align-items-end">
          <div class="flex-grow-1">
            <label class="form-label small fw-semibold"><?= e(t('common.label.name')) ?></label>
            <input type="text" name="nome" class="form-control form-control-sm"
                   placeholder="<?= e(t('contacts.categories.ph_new')) ?>" required>
          </div>
          <div>
            <label class="form-label small fw-semibold"><?= e(t('contacts.categories.field_color')) ?></label>
            <?php $palette = ['#3b82f6'=>'Blu','#8b5cf6'=>'Viola','#ec4899'=>'Rosa','#ef4444'=>'Rosso','#f97316'=>'Arancione','#22c55e'=>'Verde','#14b8a6'=>'Turchese','#64748b'=>'Grigio']; ?>
            <div class="ct-cat-color-picker">
              <input type="hidden" name="colore" value="#3b82f6" class="ct-cat-color-val">
              <div class="ct-cat-swatches">
                <?php foreach ($palette as $hex => $name): ?>
                <button type="button"
                        class="ct-cat-swatch-btn <?= $hex === '#3b82f6' ? 'active' : '' ?>"
                        data-color="<?= e($hex) ?>" style="background:<?= e($hex) ?>;"
                        title="<?= e($name) ?>"></button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div>
            <button type="submit" class="btn btn-sm btn-primary">
              <i class="fa-solid fa-plus me-1"></i><?= e(t('common.action.add')) ?>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Lista categorie -->
  <div class="card">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="app-card-icon"><i class="fa-solid fa-list"></i></span>
      <span class="fw-semibold"><?= e(t('contacts.categories.section_list')) ?></span>
    </div>
    <div class="card-body p-3">
      <div id="ct-cat-list">
        <?php $view->include('Contacts/Views/partials/categories_list', compact('categorie')); ?>
      </div>
    </div>
  </div>

</div>
</div>
</div>

<?php $view->end(); ?>
