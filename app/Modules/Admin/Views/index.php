<?php
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->pushScript('js/admin.js');
$view->start('content');

$heroSubtitle = t('admin.index.subtitle', [
    'sections' => (int) ($summary['sections'] ?? 0),
    'groups'   => (int) ($summary['groups'] ?? 0),
    'links'    => (int) ($summary['links'] ?? 0),
]);

$summaryCards = [
    [
        'label' => t('admin.index.card_areas'),
        'value' => (int) ($summary['sections'] ?? 0),
        'icon'  => 'fa-solid fa-layer-group',
        'hint'  => t('admin.index.card_areas_hint'),
    ],
    [
        'label' => t('admin.index.card_panels'),
        'value' => (int) ($summary['groups'] ?? 0),
        'icon'  => 'fa-solid fa-table-cells-large',
        'hint'  => t('admin.index.card_panels_hint'),
    ],
    [
        'label' => t('admin.index.card_links'),
        'value' => (int) ($summary['links'] ?? 0),
        'icon'  => 'fa-solid fa-arrow-up-right-from-square',
        'hint'  => t('admin.index.card_links_hint'),
    ],
    [
        'label' => t('admin.index.card_modules'),
        'value' => (int) ($summary['modules'] ?? 0),
        'icon'  => 'fa-solid fa-cubes',
        'hint'  => t('admin.index.card_modules_hint'),
    ],
];
?>

<div class="container-fluid app-page-wide">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid fa-table-cells-large',
        'adminTitle'    => t('admin.index.title'),
        'adminSubtitle' => $heroSubtitle,
    ]); ?>

    <div class="row g-3 mb-4">
        <?php foreach ($summaryCards as $card): ?>
            <div class="col-12 col-sm-6 col-xxl-3">
                <div class="card adm-card adm-index-stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="adm-index-stat-icon">
                            <i class="<?= e($card['icon']) ?>"></i>
                        </div>
                        <div>
                            <div class="adm-stat-value mb-1"><?= number_format((int) $card['value']) ?></div>
                            <div class="adm-stat-label text-muted mb-1"><?= e($card['label']) ?></div>
                            <div class="small text-muted"><?= e($card['hint']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($sections) > 1): ?>
    <div class="card adm-card mb-4">
        <div class="card-body py-2 px-3">
            <div class="adm-index-section-nav">
                <span class="adm-index-nav-label"><i class="fa-solid fa-location-dot me-1"></i><?= e(t('admin.index.jump_to')) ?></span>
                <?php foreach ($sections as $i => $section): ?>
                    <a href="#adm-section-<?= $i ?>"
                       class="adm-index-nav-pill"
                       data-bs-toggle="tooltip"
                       data-bs-placement="bottom"
                       title="<?= e($section['description']) ?>">
                        <span class="adm-index-nav-pill-eyebrow"><?= e($section['eyebrow']) ?></span>
                        <?= e($section['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card adm-card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-lg-7">
                    <label for="adm-index-filter" class="form-label fw-semibold mb-1"><?= e(t('admin.index.search_label')) ?></label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-body border-end-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="search"
                               id="adm-index-filter"
                               class="form-control border-start-0 border-end-0 ps-1"
                               placeholder="<?= e(t('admin.index.search_ph')) ?>"
                               autocomplete="off"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               title="<?= e(t('admin.index.search_tip')) ?>">
                        <button class="btn btn-outline-secondary bg-body border-start-0 d-none"
                                id="adm-index-clear"
                                type="button"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="<?= e(t('admin.index.clear_tip')) ?>">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <span class="input-group-text bg-body border-start-0 d-none d-lg-flex"
                              data-bs-toggle="tooltip"
                              data-bs-placement="top"
                              title="<?= e(t('admin.index.focus_tip')) ?>">
                            <kbd class="adm-index-kbd">/</kbd>
                        </span>
                    </div>
                    <div class="mt-2 min-height-1lh">
                        <span id="adm-index-result-count" class="small text-muted d-none">
                            <i class="fa-solid fa-filter me-1"></i><span id="adm-index-count-text"></span>
                        </span>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="adm-index-note-card rounded-4 border bg-body-tertiary p-3 h-100">
                        <div class="small text-uppercase text-body-secondary fw-semibold mb-2"><?= e(t('admin.index.coverage')) ?></div>
                        <p class="mb-2"><?= e(t('admin.index.coverage_body1')) ?></p>
                        <p class="mb-0 text-muted small"><?= e(t('admin.index.coverage_body2')) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="adm-index-empty" class="alert alert-secondary d-none" role="status">
        <i class="fa-solid fa-magnifying-glass me-2"></i>
        <?= e(t('admin.index.no_match')) ?>
    </div>

    <?php if (!empty($sections)): ?>
        <?php foreach ($sections as $i => $section): ?>
            <?php
            $sectionLinkCount = 0;
            foreach ($section['groups'] as $group) {
                $sectionLinkCount += count($group['links']);
            }
            ?>
            <section class="mb-5" id="adm-section-<?= $i ?>" data-adm-section>
                <div class="adm-index-section-header d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-2 mb-3">
                    <div>
                        <span class="adm-index-section-eyebrow"><?= e($section['eyebrow']) ?></span>
                        <h2 class="h4 mb-1 mt-1"><?= e($section['title']) ?></h2>
                        <p class="text-muted mb-0"><?= e($section['description']) ?></p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                        <span class="badge rounded-pill text-bg-light border"><?= e(t('admin.index.panels_count', ['count' => number_format(count($section['groups']))])) ?></span>
                        <span class="badge rounded-pill text-bg-light border"><?= e(t('admin.index.links_count', ['count' => number_format($sectionLinkCount)])) ?></span>
                    </div>
                </div>

                <div class="row g-2">
                    <?php foreach ($section['groups'] as $group): ?>
                        <div class="col-12 col-lg-6 col-xl-4 col-xxl-3" data-adm-group data-adm-search="<?= e($group['search_text']) ?>">
                            <article class="card adm-card adm-index-group h-100">
                                <div class="card-body adm-index-group-body d-flex flex-column">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="adm-index-group-icon">
                                            <i class="<?= e($group['icon']) ?>"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex flex-wrap align-items-center gap-1 mb-0">
                                                <h3 class="h6 mb-0 fw-semibold"><?= e($group['title']) ?></h3>
                                                <span class="badge text-bg-light border adm-index-module-badge"><?= e($group['module']) ?></span>
                                            </div>
                                            <p class="text-muted mb-0 adm-index-group-desc"><?= e($group['description']) ?></p>
                                        </div>
                                        <span class="badge rounded-pill adm-index-group-count flex-shrink-0"
                                              data-bs-toggle="tooltip"
                                              data-bs-placement="top"
                                              title="<?= e(t('admin.index.group_count_tip')) ?>">
                                            <?= number_format(count($group['links'])) ?>
                                        </span>
                                    </div>

                                    <div class="d-grid gap-1">
                                        <?php foreach ($group['links'] as $link): ?>
                                            <a href="<?= e($link['url']) ?>"
                                               class="adm-index-link text-decoration-none text-reset"
                                               data-adm-link
                                               data-adm-search="<?= e($link['search_text']) ?>"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               title="<?= e($link['tooltip']) ?>">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="adm-index-link-icon">
                                                        <i class="<?= e($link['icon']) ?>"></i>
                                                    </div>
                                                    <div class="flex-grow-1 min-w-0">
                                                        <div class="fw-semibold adm-index-link-label"><?= e($link['label']) ?></div>
                                                        <div class="adm-index-link-desc text-muted text-truncate"><?= e($link['description']) ?></div>
                                                    </div>
                                                    <i class="fa-solid fa-arrow-up-right-from-square adm-index-link-chevron flex-shrink-0"></i>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if (!empty($group['flows'])): ?>
                                        <div class="adm-index-flows mt-2 pt-2 border-top">
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($group['flows'] as $flow): ?>
                                                    <span class="badge text-bg-light border adm-index-flow"><?= e($flow) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card adm-card adm-empty">
            <i class="fa-solid fa-shield-halved fa-2x text-body-secondary mb-3"></i>
            <h2 class="h5 mb-2"><?= e(t('admin.index.empty_title')) ?></h2>
            <p class="text-muted mb-0"><?= e(t('admin.index.empty_body')) ?></p>
        </div>
    <?php endif; ?>
</div>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';

    var input      = document.getElementById('adm-index-filter');
    var clearBtn   = document.getElementById('adm-index-clear');
    var emptyState = document.getElementById('adm-index-empty');
    var countWrap  = document.getElementById('adm-index-result-count');
    var countText  = document.getElementById('adm-index-count-text');

    if (!input) { return; }

    function normalize(value) {
        return (value || '').toString().toLowerCase().trim();
    }

    function applyFilter() {
        var term = normalize(input.value);
        var visibleGroups = 0;

        document.querySelectorAll('[data-adm-section]').forEach(function (section) {
            var visibleInSection = 0;

            section.querySelectorAll('[data-adm-group]').forEach(function (group) {
                var groupSearch  = normalize(group.getAttribute('data-adm-search'));
                var groupMatches = term === '' || groupSearch.indexOf(term) !== -1;
                var visibleLinks = 0;

                group.querySelectorAll('[data-adm-link]').forEach(function (link) {
                    var linkMatches = groupMatches || term === '' || normalize(link.getAttribute('data-adm-search')).indexOf(term) !== -1;
                    link.classList.toggle('d-none', !linkMatches);
                    if (linkMatches) { visibleLinks++; }
                });

                var shouldShowGroup = term === '' || groupMatches || visibleLinks > 0;
                group.classList.toggle('d-none', !shouldShowGroup);

                if (shouldShowGroup) {
                    visibleGroups++;
                    visibleInSection++;
                }
            });

            section.classList.toggle('d-none', visibleInSection === 0);
        });

        if (emptyState) {
            emptyState.classList.toggle('d-none', visibleGroups > 0 || term === '');
        }

        /* ── Result count ── */
        if (countWrap && countText) {
            if (term !== '') {
                var noun = visibleGroups === 1 ? <?= json_encode(t('admin.index.js_panel_found')) ?> : <?= json_encode(t('admin.index.js_panels_found')) ?>;
                countText.textContent = visibleGroups + ' ' + noun;
                countWrap.classList.remove('d-none');
            } else {
                countWrap.classList.add('d-none');
            }
        }

        /* ── Clear button ── */
        if (clearBtn) {
            clearBtn.classList.toggle('d-none', term === '');
        }
    }

    input.addEventListener('input', applyFilter);

    /* ── Clear button action ── */
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            applyFilter();
            input.focus();
        });
    }

    /* ── Keyboard shortcut: / focuses search, Esc clears it ── */
    document.addEventListener('keydown', function (e) {
        var tag = document.activeElement ? document.activeElement.tagName : '';
        if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
            e.preventDefault();
            input.focus();
            input.select();
        }
        if (e.key === 'Escape' && document.activeElement === input) {
            input.value = '';
            applyFilter();
            input.blur();
        }
    });

    /* ── Smooth scroll to section anchors ── */
    document.querySelectorAll('.adm-index-nav-pill').forEach(function (pill) {
        pill.addEventListener('click', function (e) {
            var href = pill.getAttribute('href');
            if (!href || href.charAt(0) !== '#') { return; }
            var target = document.querySelector(href);
            if (!target) { return; }
            e.preventDefault();
            var offset = 80;
            var top = target.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });

    /* ── Tooltip re-init ── */
    function initIndexTooltips() {
        if (!window.bootstrap) { return; }
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initIndexTooltips, { once: true });
    } else {
        initIndexTooltips();
    }

    applyFilter();
})();
</script>

<?php $view->end(); ?>