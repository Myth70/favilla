<?php
/**
 * VISTA LISTA — Pagina principale del modulo.
 *
 * Variabili dal Controller:
 *   $items, $total, $pages, $page, $filters, $statusCounts
 *   + automatiche dal layout: $view, $user, $pageTitle, $breadcrumbs, $menuItems
 *
 * COME FUNZIONA:
 * 1. $view->layout('main') → usa il layout app/Views/layouts/main.php
 * 2. $view->start('content') / $view->end() → definisce la sezione "content"
 * 3. Il layout chiama $view->yield('content') per inserire questa sezione
 * 4. La tabella è in un partial separato, ricaricato via HTMX per filtri/paginazione
 *
 * i18n: ogni stringa user-facing passa da e(t('example.<chiave>')).
 */

$view->layout('main');

// CSS/JS proprietari del modulo (opzionale)
// $view->pushStyle('css/example.css');
// $view->pushScript('js/example.js');
?>

<?php $view->start('content'); ?>

<div class="container-fluid">

    <!-- ── Hero (header standard — NON reinventare) ───────────── -->
    <?php
    $heroButtons = '';
    if (has_permission('example.create')) {
        $heroButtons = '<a href="' . e(route('example.create')) . '" class="btn btn-primary btn-sm">'
                     . '<i class="fa-solid fa-plus me-1"></i> ' . e(t('example.actions.new')) . '</a>';
    }
    $view->include('partials/pf-hero-module', [
        'moduleName'     => t('example.title'),
        'moduleIcon'     => 'fa-solid fa-cube',
        'moduleSubtitle' => e(t('example.count_total', ['count' => $total])),
        'moduleButtons'  => $heroButtons,
    ]);
    ?>

    <!-- ── Badge contatori stato ──────────────────────────────── -->
    <div class="d-flex gap-2 mb-3">
        <span class="badge bg-success"><?= e(t('example.badges.active', ['count' => (int) ($statusCounts['active'] ?? 0)])) ?></span>
        <span class="badge bg-secondary"><?= e(t('example.badges.inactive', ['count' => (int) ($statusCounts['inactive'] ?? 0)])) ?></span>
        <span class="badge bg-warning text-dark"><?= e(t('example.badges.archived', ['count' => (int) ($statusCounts['archived'] ?? 0)])) ?></span>
    </div>

    <!-- ── Barra filtri HTMX ───────────────────────────────────── -->
    <!--
        COME FUNZIONA:
        Ogni input/select ha hx-get che punta a index() del controller.
        Il controller rileva isHtmxRequest() e restituisce SOLO il partial tabella.
        hx-target="#items-table" → sostituisce il contenuto della tabella.
        hx-push-url="true" → aggiorna l'URL del browser (back button funziona).
    -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm"
                           name="q" value="<?= e($filters['q'] ?? '') ?>"
                           placeholder="<?= e(t('example.filters.search_placeholder')) ?>"
                           hx-get="<?= e(route('example.index')) ?>"
                           hx-trigger="keyup changed delay:400ms"
                           hx-target="#items-table"
                           hx-push-url="true"
                           hx-include="[name='status']">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm"
                            hx-get="<?= e(route('example.index')) ?>"
                            hx-trigger="change"
                            hx-target="#items-table"
                            hx-push-url="true"
                            hx-include="[name='q']">
                        <option value=""><?= e(t('example.filters.all_status')) ?></option>
                        <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= e(t('example.status.active')) ?></option>
                        <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= e(t('example.status.inactive')) ?></option>
                        <option value="archived" <?= ($filters['status'] ?? '') === 'archived' ? 'selected' : '' ?>><?= e(t('example.status.archived')) ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="<?= e(route('example.index')) ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fa-solid fa-rotate-left me-1"></i> <?= e(t('example.actions.reset')) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabella (partial HTMX-swappable) ────────────────────── -->
    <div id="items-table">
        <?php $view->include('_Template/Views/partials/table', get_defined_vars()); ?>
    </div>

</div>

<?php $view->end(); ?>
