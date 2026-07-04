<?php
/**
 * Partial: pannello condivisioni per il dettaglio contatto (owner-only).
 * Variabili: $item (contatto), $shares (lista ruoli condivisi)
 */
$contattoId = (int) ($item['id'] ?? 0);
?>
<div id="ct-sharing-panel">
  <?php if (empty($shares)): ?>
    <div class="text-muted small mb-2">
      <i class="fa-solid fa-lock me-1 opacity-50"></i>
      <?= e(t('contacts.sharing.not_shared_note')) ?>
    </div>
    <a href="<?= e(route('contacts.sharing.edit', ['id' => $contattoId])) ?>"
       class="btn btn-sm btn-outline-primary">
      <i class="fa-solid fa-users me-1"></i><?= e(t('contacts.sharing.share_btn')) ?>
    </a>
  <?php else: ?>
    <div class="text-muted small mb-2">
      <i class="fa-solid fa-users me-1"></i>
      <?= e(t('contacts.sharing.shared_count', ['count' => count($shares), 'role' => count($shares) === 1 ? t('contacts.sharing.shared_count_role_one') : t('contacts.sharing.shared_count_role_many')])) ?>:
    </div>
    <div class="d-flex flex-wrap gap-2 mb-2">
      <?php foreach ($shares as $s): ?>
        <span class="badge rounded-pill bg-light text-body border d-inline-flex align-items-center gap-2 py-2 px-3">
          <i class="fa-solid fa-user-tag opacity-75" aria-hidden="true"></i>
          <span><?= e($s['role_name']) ?></span>
          <button type="button"
                  class="btn btn-sm btn-link text-danger p-0 m-0"
                  hx-delete="<?= e(route('contacts.sharing.destroy', ['id' => $contattoId, 'rid' => (int) $s['role_id']])) ?>"
                  hx-target="#ct-sharing-panel"
                  hx-swap="outerHTML"
                  hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'
                  data-app-confirm="<?= e(t('contacts.sharing.remove_confirm', ['role' => $s['role_name']])) ?>"
                  data-app-confirm-label="<?= e(t('common.action.remove')) ?>"
                  data-bs-toggle="tooltip"
                  title="<?= e(t('contacts.sharing.remove_tip')) ?>">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </span>
      <?php endforeach; ?>
    </div>
    <a href="<?= e(route('contacts.sharing.edit', ['id' => $contattoId])) ?>"
       class="btn btn-sm btn-outline-secondary">
      <i class="fa-solid fa-pen me-1"></i><?= e(t('contacts.sharing.manage_btn')) ?>
    </a>
  <?php endif; ?>
</div>
