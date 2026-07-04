<?php
/**
 * Partial HTMX: lista categorie (per gestione e aggiornamenti inline)
 * Variabili: $categorie
 */
?>
<?php if (empty($categorie)): ?>
<p class="text-muted text-center small py-3"><?= e(t('contacts.categories.empty')) ?></p>
<?php else: ?>
<?php foreach ($categorie as $cat): ?>
<div class="ct-cat-row" id="ct-cat-row-<?= (int)$cat['id'] ?>">
  <div class="ct-cat-swatch" id="color-preview-<?= (int)$cat['id'] ?>"
  style="--ct-cat-color: <?= e($cat['colore']) ?>;"></div>

  <!-- Inline edit form -->
  <form class="d-flex gap-2 align-items-center flex-grow-1"
        hx-post="<?= e(route('contacts.categories.update', ['cid' => $cat['id']])) ?>"
        hx-target="#ct-cat-list" hx-swap="innerHTML"
        hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'>
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">
    <input type="text" name="nome" value="<?= e($cat['nome']) ?>"
          class="form-control form-control-sm ct-cat-name-input" required>
    <?php
      $palette = ['#3b82f6'=>'Blu','#8b5cf6'=>'Viola','#ec4899'=>'Rosa','#ef4444'=>'Rosso','#f97316'=>'Arancione','#22c55e'=>'Verde','#14b8a6'=>'Turchese','#64748b'=>'Grigio'];
      $curColor = $cat['colore'] ?: '#3b82f6';
      $inPalette = array_key_exists($curColor, $palette);
    ?>
    <div class="ct-cat-color-picker">
      <input type="hidden" name="colore" value="<?= e($curColor) ?>" class="ct-cat-color-val">
      <div class="ct-cat-swatches">
        <?php foreach ($palette as $hex => $name): ?>
        <button type="button"
                class="ct-cat-swatch-btn <?= ($hex === $curColor || (!$inPalette && $hex === '#3b82f6')) ? 'active' : '' ?>"
                data-color="<?= e($hex) ?>" style="background:<?= e($hex) ?>;"
                title="<?= e($name) ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
    <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= e(t('contacts.categories.save_tip')) ?>" data-bs-toggle="tooltip">
      <i class="fa-solid fa-check"></i>
    </button>
  </form>

  <!-- Contatore contatti -->
  <span class="badge bg-secondary rounded-pill ct-badge-xs" title="<?= e(t('contacts.categories.count_tip')) ?>">
    <?= (int)$cat['totale_contatti'] ?>
  </span>

  <!-- Elimina -->
  <form method="POST"
        action="<?= e(route('contacts.categories.destroy', ['cid' => $cat['id']])) ?>"
      hx-post="<?= e(route('contacts.categories.destroy', ['cid' => $cat['id']])) ?>"
        hx-target="#ct-cat-list" hx-swap="innerHTML"
        hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'
        class="d-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit" class="btn btn-sm btn-outline-danger"
            data-app-confirm="<?= e(t('contacts.categories.delete_confirm', ['name' => $cat['nome']])) ?>"
            data-app-confirm-label="<?= e(t('common.action.delete')) ?>"
            data-app-confirm-class="btn-danger"
            title="<?= e(t('contacts.categories.delete_tip')) ?>" data-bs-toggle="tooltip">
      <i class="fa-solid fa-trash"></i>
    </button>
  </form>
</div>
<?php endforeach; ?>
<?php endif; ?>
