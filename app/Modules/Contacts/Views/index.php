<?php
/**
 * Variabili: $items, $total, $pages, $page, $perPage, $filters,
 *            $categorie, $tags, $stats, $prossime
 */
$view->layout('main');
$view->pushStyle('css/contacts.css');
$view->pushScript('js/contacts.js');

use App\Modules\Contacts\Services\ContactsService;
use App\Modules\Auth\Helpers\AvatarHelper;
?>
<?php $view->start('content'); ?>

<?php
$ctProfileName = $user['name'] ?? t('common.user.fallback_name');
$ctAvatarUrl   = AvatarHelper::url($_SESSION['user_avatar'] ?? null);
$ctInitials    = AvatarHelper::initials($ctProfileName);

$ctHeroStats = [
  ['value' => (int) ($stats['totale'] ?? 0), 'label' => t('contacts.stats.contacts'), 'icon' => 'fa-solid fa-address-book', 'color' => 'primary'],
  ['value' => (int) ($stats['preferiti'] ?? 0), 'label' => t('contacts.stats.favorites'), 'icon' => 'fa-solid fa-star', 'color' => 'warning'],
  ['value' => count($categorie ?? []), 'label' => t('contacts.stats.categories'), 'icon' => 'fa-solid fa-tags', 'color' => 'info'],
  ['value' => count($prossime ?? []), 'label' => t('contacts.stats.recurrences'), 'icon' => 'fa-solid fa-bell', 'color' => 'success'],
];
?>

<!-- Fire-and-forget reminder processor -->
<form id="ct-reminder-trigger"
      hx-post="<?= e(route('contacts.reminders.process')) ?>"
      hx-trigger="load delay:800ms"
      hx-swap="none"
  class="d-none">
  <?= csrf_field() ?>
</form>

<div class="container-fluid">

  <?php
  $ctButtons = '<a href="' . e(route('contacts.categories.index')) . '" class="btn btn-sm btn-outline-secondary">' .
               '<i class="fa-solid fa-tags me-1"></i>' . e(t('contacts.action.categories')) . '</a>';
  if (has_permission('contacts.import')) {
    $ctButtons .= '<a href="' . e(route('contacts.import.index')) . '" class="btn btn-sm btn-outline-primary">' .
                  '<i class="fa-solid fa-file-import me-1"></i>' . e(t('contacts.action.import')) . '</a>';
  }
  if (has_permission('contacts.create')) {
    $ctButtons .= '<a href="' . e(route('contacts.create')) . '" class="btn btn-sm btn-primary">' .
                  '<i class="fa-solid fa-plus me-1"></i>' . e(t('contacts.action.new')) . '</a>';
  }
  $view->include('partials/pf-hero-user', [
    'userName'     => t('contacts.title'),
    'userSubtitle' => $ctProfileName . ' - ' . t('contacts.subtitle'),
    'userAvatar'   => $ctAvatarUrl ?? null,
    'userInitials' => $ctInitials,
    'userStats'    => $ctHeroStats,
    'userButtons'  => $ctButtons,
  ]);
  ?>

  <!-- ── Ricorrenze + Filtri (card unificata con stile profile) ────────── -->
  <div class="card shadow-sm mb-3 ct-index-card">
    <div class="card-body">
      <div class="app-section-grid">

        <!-- §1 Prossime ricorrenze (solo se ce ne sono) -->
        <?php if (!empty($prossime)): ?>
        <section class="app-section">
          <header class="app-section-subhead">
            <i class="fa-solid fa-bell"></i>
            <span><?= e(t('contacts.upcoming.title')) ?></span>
            <small class="app-section-subhead-hint"><?= e(t('contacts.upcoming.incoming', ['count' => count($prossime)])) ?></small>
          </header>
          <div class="ct-upcoming-bar">
            <?php foreach ($prossime as $p):
              $urgenza = $p['urgenza'] ?? 'lontano';
              $icone   = ['compleanno' => '🎂', 'anniversario' => '💍', 'evento' => '📅'];
              $icona   = $icone[$p['tipo']] ?? '📅';
              $nomeC   = trim($p['nome'] . ' ' . ($p['cognome'] ?? ''));
              $giorni  = (int) $p['giorni_mancanti'];
              $badgeLbl = $giorni === 0 ? t('contacts.upcoming.today') : t('contacts.upcoming.in_days', ['days' => $giorni]);
            ?>
            <a href="<?= e(route('contacts.show', ['id' => $p['contatto_id']])) ?>" class="ct-upcoming-chip">
              <span><?= $icona ?></span>
              <span>
                <div class="fw-semibold ct-upcoming-name"><?= e($nomeC) ?></div>
                <div class="ct-upcoming-title"><?= e($p['titolo']) ?></div>
              </span>
              <span class="ct-uc-badge ct-badge-<?= $urgenza ?>"><?= $badgeLbl ?></span>
            </a>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- §2 Filtri (ricerca + pills categorie + preferiti) -->
        <section class="app-section">
          <header class="app-section-subhead">
            <i class="fa-solid fa-filter"></i>
            <span><?= e(t('contacts.filters.title')) ?></span>
          </header>

          <div class="input-group input-group-sm mb-2">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" class="form-control"
                   name="q" value="<?= e($filters['q'] ?? '') ?>"
                   placeholder="<?= e(t('contacts.filters.search_placeholder')) ?>"
                   autocomplete="off" data-ct-search
                   hx-get="<?= e(route('contacts.index')) ?>"
                   hx-trigger="keyup changed delay:350ms, search"
                   hx-target="#ct-grid-wrap"
                   hx-push-url="true"
                   hx-include="[name='categoria_id'],[name='preferiti'],[name='sort'],[name='dir']">
            <?php if (!empty($filters['q'])): ?>
            <a href="<?= e(route('contacts.index')) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('contacts.filters.clear_search')) ?>" aria-label="<?= e(t('contacts.filters.clear_search')) ?>">
              <i class="fa-solid fa-xmark"></i>
            </a>
            <?php endif; ?>
          </div>

          <!-- Hidden inputs per i filtri -->
          <input type="hidden" name="categoria_id" value="<?= e($filters['categoria_id'] ?? '') ?>">
          <input type="hidden" name="preferiti"    value="<?= !empty($filters['preferiti']) ? 1 : '' ?>">
          <input type="hidden" name="sort"         value="<?= e($filters['sort'] ?? 'nome') ?>">
          <input type="hidden" name="dir"          value="<?= e($filters['dir'] ?? 'asc') ?>">

          <div class="ct-filter-bar">
            <button class="ct-cat-pill <?= empty($filters['categoria_id']) ? 'active' : '' ?>"
              data-cat-id="0">
              <?= e(t('contacts.filters.all_categories')) ?>
            </button>
            <?php foreach ($categorie as $cat): ?>
            <button class="ct-cat-pill ct-cat-pill-custom <?= ((int)($filters['categoria_id'] ?? 0) === (int)$cat['id']) ? 'active' : '' ?>"
                    data-cat-id="<?= (int)$cat['id'] ?>"
              style="--ct-pill-color: <?= e($cat['colore']) ?>;">
              <span class="ct-cat-pill-dot"></span>
              <?= e($cat['nome']) ?>
              <?php if ($cat['totale_contatti'] > 0): ?>
              <span class="ct-pill-count">(<?= (int)$cat['totale_contatti'] ?>)</span>
              <?php endif; ?>
            </button>
            <?php endforeach; ?>

            <?php if (!empty($categorie)): ?><span class="text-muted mx-1">|</span><?php endif; ?>

            <button class="ct-cat-pill ct-cat-pill-custom ct-cat-pill-star <?= !empty($filters['preferiti']) ? 'active' : '' ?>"
              data-ct-preferiti-toggle>
              <i class="fa-<?= !empty($filters['preferiti']) ? 'solid' : 'regular' ?> fa-star"></i>&nbsp;<?= e(t('contacts.filters.favorites')) ?>
            </button>
          </div>
        </section>

        <!-- §3 Ordina -->
        <section class="app-section">
          <header class="app-section-subhead">
            <i class="fa-solid fa-arrow-up-z-a"></i>
            <span><?= e(t('contacts.sort.title')) ?></span>
          </header>
          <div class="ct-filter-bar">
            <?php
            $ctCurrentSort = $filters['sort'] ?? 'nome';
            $ctCurrentDir  = strtolower($filters['dir'] ?? 'asc');
            $ctSortOptions = [
                'nome'       => t('contacts.fields.nome'),
                'cognome'    => t('contacts.fields.cognome'),
                'azienda'    => t('contacts.fields.azienda'),
                'created_at' => t('contacts.fields.data'),
            ];
            foreach ($ctSortOptions as $ctCol => $ctLabel):
                $ctIsActive = $ctCurrentSort === $ctCol;
            ?>
            <button class="ct-cat-pill <?= $ctIsActive ? 'active' : '' ?>"
                    data-ct-sort-col="<?= $ctCol ?>">
              <?= e($ctLabel) ?>
              <?php if ($ctIsActive): ?>
                <i class="fa-solid <?= $ctCurrentDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down' ?> ms-1 ct-sort-arrow" style="font-size:.7em" aria-hidden="true"></i>
              <?php endif; ?>
            </button>
            <?php endforeach; ?>
          </div>
        </section>

      </div>
    </div>
  </div>

  <!-- ── Griglia contatti (HTMX target) ──────────────────────── -->
  <div id="ct-grid-wrap">
    <?php $view->include('Contacts/Views/partials/cards', get_defined_vars()); ?>
  </div>

</div>

<?php $view->end(); ?>
