<?php $view->layout('main'); ?>
<?php $view->start('content'); ?>
<?php
$query = (string) ($query ?? '');
$schemaReady = (bool) ($schemaReady ?? false);
$results = $results ?? [];
$selectedChunk = $selectedChunk ?? null;
$topicsByModule = $topicsByModule ?? [];
$quickPrompts = $quickPrompts ?? [];
$contextModule = $contextModule ?? null;
$contextModuleTitle = $contextModuleTitle ?? $contextModule;
$stats = $stats ?? ['entries' => 0, 'aliases' => 0, 'modules' => 0, 'topics' => 0];
$canAdminHelp = has_permission('helponline.admin');

$heroButtons = '';
if ($query !== '') {
    $heroButtons .= '<a href="' . e(route('helponline.index')) . '" class="btn btn-sm btn-outline-secondary">'
        . '<i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>' . e(t('helponline.user.reset')) . '</a>';
}
$heroButtons .= '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="offcanvas" data-bs-target="#ho-offcanvas">'
    . '<i class="fa-solid fa-comments me-1" aria-hidden="true"></i>' . e(t('helponline.user.ask_inline')) . '</button>';
if ($canAdminHelp) {
    $heroButtons .= '<a href="' . e(route('helponline.admin.index')) . '" class="btn btn-sm btn-outline-secondary">'
        . '<i class="fa-solid fa-gauge-high me-1" aria-hidden="true"></i>' . e(t('helponline.user.dashboard_admin')) . '</a>';
}

$highlight = static function (string $text, string $query): string {
    $escaped = e($text);
    $query = trim($query);
    if ($query === '') {
        return $escaped;
    }

    // Match against the already-escaped haystack: escape the needle the same
    // way before quoting so entities like &amp; and quotes still highlight.
    $needle = preg_quote(e($query), '/');
    return preg_replace('/(' . $needle . ')/iu', '<mark>$1</mark>', $escaped) ?? $escaped;
};

$confidenceTone = static function (int $confidence): string {
    if ($confidence >= 60) return 'success';
    if ($confidence >= 30) return 'warning';
    return 'secondary';
};
?>

<div class="container-fluid app-page-wide">
    <?php $view->include('partials/pf-hero-module', [
        'moduleName' => t('helponline.title'),
        'moduleIcon' => 'fa-solid fa-circle-question',
        'moduleSubtitle' => $contextModuleTitle !== null
            ? t('helponline.user.subtitle_context', ['module' => e((string) $contextModuleTitle)])
            : t('helponline.user.subtitle_general'),
        'moduleButtons' => $heroButtons,
    ]); ?>

    <?php if (!$schemaReady): ?>
        <div class="alert alert-warning shadow-sm d-flex align-items-start gap-2 mb-3">
            <i class="fa-solid fa-triangle-exclamation mt-1" aria-hidden="true"></i>
            <div>
                <div class="fw-semibold"><?= e(t('helponline.user.schema_title')) ?></div>
                <div class="small">
                    <?php if ($canAdminHelp): ?>
                        <?= t('helponline.user.schema_admin') ?>
                    <?php else: ?>
                        <?= t('helponline.user.schema_user') ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php elseif (($stats['entries'] ?? 0) > 0): ?>
        <div class="ho-page-stats mb-3" role="group" aria-label="<?= e(t('helponline.user.stats_aria')) ?>">
            <div class="ho-stat">
                <span class="ho-stat-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></span>
                <div>
                    <div class="ho-stat-value"><?= (int) $stats['entries'] ?></div>
                    <div class="ho-stat-label"><?= e(t('helponline.user.stat_questions')) ?></div>
                </div>
            </div>
            <div class="ho-stat">
                <span class="ho-stat-icon"><i class="fa-solid fa-tags" aria-hidden="true"></i></span>
                <div>
                    <div class="ho-stat-value"><?= (int) $stats['aliases'] ?></div>
                    <div class="ho-stat-label"><?= e(t('helponline.user.stat_synonyms')) ?></div>
                </div>
            </div>
            <div class="ho-stat">
                <span class="ho-stat-icon"><i class="fa-solid fa-cubes" aria-hidden="true"></i></span>
                <div>
                    <div class="ho-stat-value"><?= (int) $stats['modules'] ?></div>
                    <div class="ho-stat-label"><?= e(t('helponline.user.stat_modules')) ?></div>
                </div>
            </div>
            <div class="ho-stat">
                <span class="ho-stat-icon"><i class="fa-solid fa-bookmark" aria-hidden="true"></i></span>
                <div>
                    <div class="ho-stat-value"><?= (int) $stats['topics'] ?></div>
                    <div class="ho-stat-label"><?= e(t('helponline.user.stat_topics')) ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3 ho-page-search-card">
        <div class="card-body">
            <form method="GET" action="<?= e(route('helponline.index')) ?>" class="ho-page-search-form">
                <label for="ho-page-search" class="visually-hidden"><?= e(t('helponline.user.search_label')) ?></label>
                <div class="ho-page-search-wrap">
                    <i class="fa-solid fa-magnifying-glass ho-page-search-icon" aria-hidden="true"></i>
                    <input type="search"
                           id="ho-page-search"
                           name="q"
                           class="ho-page-search-input"
                           placeholder="<?= e(t('helponline.user.search_placeholder')) ?>"
                           value="<?= e($query) ?>"
                           autocomplete="off"
                           autofocus>
                    <?php if ($query !== ''): ?>
                        <a href="<?= e(route('helponline.index')) ?>"
                           class="ho-page-search-clear"
                           title="<?= e(t('helponline.user.clear_search')) ?>"
                           aria-label="<?= e(t('helponline.user.clear_search')) ?>">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary ho-page-search-btn">
                        <i class="fa-solid fa-arrow-right d-md-none" aria-hidden="true"></i>
                        <span class="d-none d-md-inline"><?= e(t('helponline.user.search_btn')) ?></span>
                    </button>
                </div>
                <div class="ho-page-search-hint small text-muted mt-2">
                    <i class="fa-regular fa-keyboard me-1" aria-hidden="true"></i>
                    <?= t('helponline.user.search_hint') ?>
                </div>
            </form>

            <?php if (!empty($quickPrompts) && $query === ''): ?>
                <div class="ho-page-prompts mt-3">
                    <div class="ho-section-eyebrow">
                        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                        <span><?= e(t('helponline.user.quick_questions')) ?><?= $contextModuleTitle !== null ? ' · ' . e($contextModuleTitle) : '' ?></span>
                    </div>
                    <div class="ho-suggestion-chips">
                        <?php foreach ($quickPrompts as $prompt): ?>
                            <?php
                            $promptLabel = is_array($prompt) ? (string) ($prompt['label'] ?? $prompt['message'] ?? '') : (string) $prompt;
                            $promptMessage = is_array($prompt) ? (string) ($prompt['message'] ?? $promptLabel) : (string) $prompt;
                            $promptUrl = is_array($prompt) ? (string) ($prompt['url'] ?? '') : '';
                            $href = $promptUrl !== ''
                                ? $promptUrl
                                : route('helponline.index') . '?' . http_build_query(['q' => $promptMessage]);
                            ?>
                            <a href="<?= e($href) ?>"
                               class="ho-chip">
                                <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                                <span><?= e($promptLabel) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <?php if ($selectedChunk !== null): ?>
                <article class="card shadow-sm mb-3 ho-answer-card">
                    <div class="card-body">
                        <header class="ho-answer-header">
                            <div class="ho-answer-avatar" aria-hidden="true">
                                <i class="fa-solid fa-circle-question"></i>
                            </div>
                            <div class="ho-answer-meta">
                                <div class="ho-answer-eyebrow">
                                    <span class="badge text-bg-light border"><?= e((string) ($selectedChunk['module_title'] ?? $selectedChunk['module_name'] ?? t('helponline.user.guide_badge'))) ?></span>
                                </div>
                                <h2 class="h4 mb-0 mt-1"><?= e((string) ($selectedChunk['title'] ?? '')) ?></h2>
                            </div>
                            <div class="ho-answer-actions">
                                <?php
                                $confidence = (int) ($selectedChunk['confidence'] ?? 0);
                                $tone = $confidenceTone($confidence);
                                ?>
                                <?php if ($confidence > 0): ?>
                                    <span class="badge text-bg-<?= e($tone) ?>" title="<?= e(t('helponline.user.confidence')) ?>">
                                        <i class="fa-solid fa-bolt me-1" aria-hidden="true"></i><?= $confidence ?>%
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($selectedChunk['target_url'])): ?>
                                    <a href="<?= e((string) $selectedChunk['target_url']) ?>" class="btn btn-sm btn-primary">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1" aria-hidden="true"></i><?= e(t('helponline.user.open_module')) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </header>

                        <div class="ho-answer-html mt-3"><?= $selectedChunk['body_html'] ?? '' ?></div>

                        <?php if (!empty($selectedChunk['related'])): ?>
                            <div class="ho-answer-related">
                                <div class="ho-section-eyebrow">
                                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                                    <span><?= e(t('helponline.user.related')) ?></span>
                                </div>
                                <div class="ho-topic-list">
                                    <?php foreach ($selectedChunk['related'] as $related): ?>
                                        <a href="<?= e((string) $related['url']) ?>" class="ho-topic">
                                            <div class="ho-topic-icon" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
                                            <div class="ho-topic-text">
                                                <div class="ho-topic-title"><?= e((string) $related['title']) ?></div>
                                                <div class="ho-topic-sub"><?= e((string) ($related['excerpt'] ?? '')) ?></div>
                                            </div>
                                            <i class="fa-solid fa-chevron-right ho-topic-chevron" aria-hidden="true"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endif; ?>

            <?php if ($query !== ''): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="fw-semibold">
                            <i class="fa-solid fa-magnifying-glass me-1 text-secondary" aria-hidden="true"></i>
                            <?= t('helponline.user.results_for', ['query' => e($query)]) ?>
                        </span>
                        <span class="small text-muted"><?= e(t('helponline.user.results_count', ['count' => count($results)])) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($results)): ?>
                            <div class="ho-empty-state">
                                <i class="fa-solid fa-magnifying-glass fa-2x mb-3 opacity-50" aria-hidden="true"></i>
                                <div class="fw-semibold mb-1"><?= e(t('helponline.user.no_answer_title')) ?></div>
                                <p class="text-muted small mb-3"><?= e(t('helponline.user.no_answer_text')) ?></p>
                                <div class="d-flex gap-2 justify-content-center flex-wrap">
                                    <a href="<?= e(route('helponline.index')) ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i><?= e(t('helponline.user.clear_search')) ?>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="offcanvas" data-bs-target="#ho-offcanvas">
                                        <i class="fa-solid fa-comments me-1" aria-hidden="true"></i><?= e(t('helponline.user.ask_inline')) ?>
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <ol class="ho-results-list">
                                <?php foreach ($results as $result): ?>
                                    <?php if (($selectedChunk['id'] ?? null) === ($result['id'] ?? null)) { continue; } ?>
                                    <?php
                                    $rConf = (int) ($result['confidence'] ?? 0);
                                    $rTitle = (string) ($result['title'] ?? '');
                                    ?>
                                    <li class="ho-result">
                                        <a href="<?= e((string) $result['help_url']) ?>" class="ho-result-link">
                                            <div class="ho-result-main">
                                                <div class="ho-result-eyebrow">
                                                    <span class="badge text-bg-light border"><?= e((string) ($result['module_title'] ?? $result['module_name'] ?? 'Guida')) ?></span>
                                                </div>
                                                <div class="ho-result-title"><?= $highlight($rTitle, $query) ?></div>
                                                <div class="ho-result-excerpt"><?= $highlight((string) ($result['excerpt'] ?? ''), $query) ?></div>
                                            </div>
                                            <div class="ho-result-meta">
                                                <span class="badge text-bg-<?= e($confidenceTone($rConf)) ?>"><?= $rConf ?>%</span>
                                                <i class="fa-solid fa-chevron-right ho-result-chevron" aria-hidden="true"></i>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (empty($topicsByModule)): ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="ho-empty-state">
                            <i class="fa-solid fa-book-open fa-2x mb-3 opacity-50" aria-hidden="true"></i>
                            <div class="fw-semibold mb-1"><?= e(t('helponline.user.no_topics_title')) ?></div>
                            <p class="text-muted small mb-0">
                                <?php if ($canAdminHelp): ?>
                                    <?= e(t('helponline.user.no_topics_admin')) ?>
                                <?php else: ?>
                                    <?= e(t('helponline.user.no_topics_user')) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($topicsByModule as $moduleName => $moduleTopics): ?>
                    <section class="card shadow-sm mb-3 ho-module-section"
                             data-ho-module-section="<?= e((string) $moduleName) ?>"
                             id="ho-module-<?= e(preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $moduleName))) ?>">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">
                                <i class="fa-solid fa-folder-open me-2 text-secondary" aria-hidden="true"></i>
                                <?= e((string) $moduleName) ?>
                            </span>
                            <span class="small text-muted"><?= e(t('helponline.user.module_topics_count', ['count' => count($moduleTopics)])) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <?php foreach ($moduleTopics as $topic): ?>
                                    <div class="col-12 col-md-6">
                                        <a href="<?= e((string) $topic['url']) ?>" class="ho-topic h-100">
                                            <div class="ho-topic-icon" aria-hidden="true"><i class="fa-solid fa-book-open"></i></div>
                                            <div class="ho-topic-text">
                                                <div class="ho-topic-title"><?= e((string) $topic['title']) ?></div>
                                                <div class="ho-topic-sub"><?= e((string) $topic['subtitle']) ?></div>
                                            </div>
                                            <i class="fa-solid fa-chevron-right ho-topic-chevron" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                                <?php /* nota: nei topic groupati per modulo (index full) il modulo e' gia' nell'header della sezione, no eyebrow duplicato */ ?>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <aside class="col-12 col-xl-4">
            <div class="ho-page-rail">
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="ho-section-eyebrow mb-2">
                            <i class="fa-solid fa-compass" aria-hidden="true"></i>
                            <span><?= e(t('helponline.user.navigate_module')) ?></span>
                        </div>
                        <?php if (empty($topicsByModule)): ?>
                            <div class="text-muted small"><?= e(t('helponline.user.no_module_indexed')) ?></div>
                        <?php else: ?>
                            <div class="ho-module-tags">
                                <?php foreach ($topicsByModule as $moduleName => $moduleTopics): ?>
                                    <?php $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $moduleName)); ?>
                                    <a href="<?= e(route('helponline.index')) ?>#ho-module-<?= e($slug) ?>"
                                       class="ho-module-tag"
                                       data-ho-module="<?= e((string) $moduleName) ?>"
                                       title="<?= e(t('helponline.user.goto_section', ['module' => (string) $moduleName])) ?>">
                                        <?= e((string) $moduleName) ?>
                                        <span class="ho-module-tag-count"><?= count($moduleTopics) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="ho-section-eyebrow mb-2">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span><?= e(t('helponline.user.suggestions')) ?></span>
                        </div>
                        <ul class="ho-tips list-unstyled mb-0 small">
                            <li>
                                <i class="fa-solid fa-check text-success me-2" aria-hidden="true"></i>
                                <?= t('helponline.user.tip1') ?>
                            </li>
                            <li>
                                <i class="fa-solid fa-check text-success me-2" aria-hidden="true"></i>
                                <?= t('helponline.user.tip2') ?>
                            </li>
                            <li>
                                <i class="fa-solid fa-check text-success me-2" aria-hidden="true"></i>
                                <?= t('helponline.user.tip3') ?>
                            </li>
                            <li>
                                <i class="fa-solid fa-check text-success me-2" aria-hidden="true"></i>
                                <?= t('helponline.user.tip4') ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <?php if ($canAdminHelp): ?>
                    <div class="card shadow-sm ho-admin-card">
                        <div class="card-body">
                            <div class="ho-section-eyebrow mb-2">
                                <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                                <span><?= e(t('helponline.user.admin_section')) ?></span>
                            </div>
                            <p class="small text-muted mb-2"><?= e(t('helponline.user.admin_desc')) ?></p>
                            <a href="<?= e(route('helponline.admin.index')) ?>" class="btn btn-sm btn-outline-primary w-100">
                                <i class="fa-solid fa-gauge-high me-1" aria-hidden="true"></i><?= e(t('helponline.user.open_dashboard')) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    function flashSection(section) {
        if (!section) return;
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        section.classList.add('ho-module-flash');
        setTimeout(function () { section.classList.remove('ho-module-flash'); }, 1200);
    }

    function findSectionByName(name) {
        if (!name) return null;
        return document.querySelector('[data-ho-module-section="' + name.replace(/"/g, '\\"') + '"]');
    }

    document.addEventListener('keydown', function (event) {
        var t = event.target;
        var typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);
        if (typing || event.ctrlKey || event.altKey || event.metaKey) return;
        if (event.key === '/') {
            var input = document.getElementById('ho-page-search');
            if (input) {
                event.preventDefault();
                input.focus();
                input.select();
            }
        }
    });

    // Click on a module tag in the sidebar:
    //  - same page (no query active): scroll + flash, no navigation
    //  - query view (sections not in DOM): let the browser navigate to /help#ho-module-X
    document.querySelectorAll('[data-ho-module]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            var section = findSectionByName(link.getAttribute('data-ho-module'));
            if (section) {
                e.preventDefault();
                flashSection(section);
            }
        });
    });

    // On load with #ho-module-X hash, flash the targeted section.
    if (window.location.hash.indexOf('#ho-module-') === 0) {
        var target = document.getElementById(window.location.hash.slice(1));
        if (target) {
            // Defer slightly so the browser default scroll completes first.
            setTimeout(function () { flashSection(target); }, 60);
        }
    }
})();
</script>

<?php $view->end(); ?>
