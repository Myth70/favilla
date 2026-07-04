<?php
/**
 * Partial HTMX — griglia card contatti
 * Variabili: $items, $total, $pages, $page, $perPage, $filters
 */
use App\Modules\Contacts\Helpers\ContactsHelper;
use App\Modules\Contacts\Services\ContactsService;
?>

<?php if (empty($items)): ?>
<div class="ct-empty">
  <i class="fa-solid fa-address-book ct-empty-icon"></i>
  <?php if (!empty($filters['q'])): ?>
    <p class="mb-1 fw-semibold"><?= e(t('contacts.cards.no_results', ['q' => $filters['q']])) ?></p>
    <p class="small text-muted"><?= e(t('contacts.cards.no_results_help')) ?></p>
  <?php elseif (!empty($filters['preferiti'])): ?>
    <p class="mb-1 fw-semibold"><?= e(t('contacts.cards.no_favorites')) ?></p>
    <p class="small text-muted"><?= e(t('contacts.cards.no_favorites_help')) ?></p>
  <?php else: ?>
    <p class="mb-2 fw-semibold"><?= e(t('contacts.cards.empty')) ?></p>
    <?php if (has_permission('contacts.create')): ?>
    <a href="<?= e(route('contacts.create')) ?>" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus me-1"></i><?= e(t('contacts.cards.add_first_btn')) ?>
    </a>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php return; ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-2 px-1">
  <small class="text-muted">
    <?= e($total === 1 ? t('contacts.cards.count_one') : t('contacts.cards.count_many', ['count' => $total])) ?>
    <?php if (!empty($filters['q'])): ?>
      <?= e(t('contacts.cards.count_for', ['q' => $filters['q']])) ?>
    <?php endif; ?>
  </small>
</div>

<div class="ct-grid mb-3">
<?php foreach ($items as $item):
  $nome        = $item['nome'];
  $cognome     = $item['cognome'] ?? '';
  $nomeCompleto = trim($nome . ' ' . $cognome);
  $initials    = ContactsService::initials($nome, $cognome);
  $color       = ContactsService::avatarColor($nome);
  $avatarUrl   = ContactsHelper::avatarUrl($item);
?>
<?php $isOwner = !isset($item['is_owner']) || (int) $item['is_owner'] === 1; ?>
<div class="ct-card<?= $isOwner ? '' : ' ct-card-shared' ?>">
  <!-- Header -->
  <div class="ct-card-header">
    <div class="ct-avatar ct-avatar-dynamic" style="--ct-avatar-bg: <?= $avatarUrl ? 'transparent' : e($color) ?>;">
      <?php if ($avatarUrl): ?>
        <img src="<?= e($avatarUrl) ?>" alt="<?= e($nomeCompleto) ?>">
      <?php else: ?>
        <?= e($initials) ?>
      <?php endif; ?>
    </div>
    <div class="ct-card-main">
      <div class="ct-card-name d-flex align-items-center gap-1">
        <a href="<?= e(route('contacts.show', ['id' => $item['id']])) ?>"
           class="stretched-link">
          <?= e($nomeCompleto) ?>
        </a>
        <?php if (!$isOwner): ?>
          <i class="fa-solid fa-users text-muted ms-1"
             data-bs-toggle="tooltip"
             title="<?= !empty($item['owner_name']) ? e(t('contacts.cards.shared_by_tip', ['name' => $item['owner_name']])) : e(t('contacts.cards.shared_tip')) ?>"
             aria-label="<?= e(t('contacts.cards.shared_aria')) ?>"></i>
        <?php endif; ?>
      </div>
      <?php if (!empty($item['azienda']) || !empty($item['ruolo'])): ?>
      <div class="ct-card-sub">
        <?= e(implode(' · ', array_filter([$item['ruolo'], $item['azienda']]))) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($item['preferito']): ?>
    <i class="fa-solid fa-star ct-star-indicator"></i>
    <?php endif; ?>
  </div>

  <!-- Info rapide -->
  <?php if (!empty($item['email']) || !empty($item['telefono'])): ?>
  <div class="ct-card-info">
    <?php if (!empty($item['email'])): ?>
    <div class="ct-card-meta">
      <i class="fa-solid fa-envelope ct-meta-icon"></i>
      <span class="ct-text-ellipsis"><?= e($item['email']) ?></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($item['telefono'])): ?>
    <div class="ct-card-meta">
      <i class="fa-solid fa-phone ct-meta-icon"></i>
      <?= e($item['telefono']) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Footer: categoria + ricorrenze badge + azioni -->
  <div class="ct-card-footer ct-card-footer-top">
    <div class="d-flex align-items-center gap-1 flex-wrap">
      <?php if (!empty($item['categoria_nome'])): ?>
      <span class="badge rounded-pill ct-category-badge"
            style="--ct-cat-color: <?= e($item['categoria_colore'] ?? '#6c757d') ?>;">
        <?= e($item['categoria_nome']) ?>
      </span>
      <?php endif; ?>
      <?php if ((int)$item['num_ricorrenze'] > 0): ?>
      <span class="badge rounded-pill ct-rec-badge" title="<?= e(t('contacts.cards.rec_tip')) ?>">
        <i class="fa-solid fa-bell me-1"></i><?= (int)$item['num_ricorrenze'] ?>
      </span>
      <?php endif; ?>
    </div>
    <div class="ct-card-actions">
      <?php if ($isOwner && has_permission('contacts.edit')): ?>
      <a href="<?= e(route('contacts.edit', ['id' => $item['id']])) ?>"
         class="btn btn-xs btn-outline-secondary ct-card-action-btn"
         title="<?= e(t('contacts.cards.edit_tip')) ?>" data-bs-toggle="tooltip">
        <i class="fa-solid fa-pen"></i>
      </a>
      <?php endif; ?>
      <?php if ($isOwner && has_permission('contacts.delete')): ?>
      <form method="POST"
            action="<?= e(route('contacts.destroy', ['id' => $item['id']])) ?>"
            class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="DELETE">
        <button type="submit"
                class="btn btn-xs btn-outline-danger ct-card-action-btn"
                data-app-confirm="<?= e(t('contacts.cards.delete_confirm', ['name' => $nomeCompleto])) ?>"
                data-app-confirm-label="<?= e(t('common.action.delete')) ?>"
                title="<?= e(t('contacts.cards.delete_tip')) ?>" data-bs-toggle="tooltip">
          <i class="fa-solid fa-trash"></i>
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Paginazione -->
<?php if ($pages > 1): ?>
<nav class="d-flex justify-content-between align-items-center px-1 mb-2">
  <small class="text-muted"><?= e(t('contacts.cards.pagination', ['page' => $page, 'pages' => $pages])) ?></small>
  <ul class="pagination pagination-sm mb-0">
    <?php $base = array_merge($filters, []); ?>
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <?php $qs = http_build_query(array_merge($base, ['page' => $page - 1])); ?>
      <a class="page-link" href="<?= e(route('contacts.index')) ?>?<?= e($qs) ?>"
         hx-get="<?= e(route('contacts.index')) ?>?<?= e($qs) ?>"
         hx-target="#ct-grid-wrap" hx-push-url="true">&lsaquo;</a>
    </li>
    <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
      <?php $qs = http_build_query(array_merge($base, ['page' => $i])); ?>
      <a class="page-link" href="<?= e(route('contacts.index')) ?>?<?= e($qs) ?>"
         hx-get="<?= e(route('contacts.index')) ?>?<?= e($qs) ?>"
         hx-target="#ct-grid-wrap" hx-push-url="true"><?= $i ?></a>
    </li>
    <?php endfor; ?>
    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
      <?php $qs = http_build_query(array_merge($base, ['page' => $page + 1])); ?>
      <a class="page-link" href="<?= e(route('contacts.index')) ?>?<?= e($qs) ?>"
         hx-get="<?= e(route('contacts.index')) ?>?<?= e($qs) ?>"
         hx-target="#ct-grid-wrap" hx-push-url="true">&rsaquo;</a>
    </li>
  </ul>
</nav>
<?php endif; ?>
