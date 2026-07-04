<?php
/**
 * Breadcrumb partial.
 * Variables: $breadcrumbs (array of ['label' => string, 'url' => string|null, 'route' => string|null, 'params' => array])
 *
 * Usage from controller:
 *   $this->render('...', [
 *       'breadcrumbs' => [
 *           ['label' => 'Home', 'url' => route('home.index')],    // pre-resolved URL
 *           ['label' => 'Utenti', 'route' => 'admin.users.index'], // named route (resolved at render)
 *           ['label' => 'Dettaglio'],  // last item has no url (active)
 *       ]
 *   ]);
 */
if (empty($breadcrumbs)) return;
?>
<nav aria-label="<?= e(t('common.chrome.breadcrumb_nav')) ?>" class="app-breadcrumb-nav">
    <ol class="breadcrumb app-breadcrumb mb-0 small">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <?php $isLast = ($i === count($breadcrumbs) - 1); ?>
            <?php if ($isLast): ?>
                <li class="breadcrumb-item app-breadcrumb-item active" aria-current="page">
                    <span class="app-breadcrumb-current">
                        <?= e($crumb['label']) ?>
                    </span>
                </li>
            <?php else: ?>
                <li class="breadcrumb-item app-breadcrumb-item">
                    <?php
                        $href = '#';
                        if (!empty($crumb['url'])) {
                            $href = $crumb['url'];
                        } elseif (!empty($crumb['route'])) {
                            $href = route($crumb['route'], $crumb['params'] ?? []);
                        }
                    ?>
                    <?php if ($href !== '#'): ?>
                        <a href="<?= e($href) ?>"
                           class="app-breadcrumb-pill"
                           data-bs-toggle="tooltip"
                           data-bs-placement="bottom"
                           title="<?= e(t('common.tooltip.go_to', ['label' => $crumb['label']])) ?>">
                            <?= e($crumb['label']) ?>
                        </a>
                    <?php else: ?>
                        <span class="app-breadcrumb-pill app-breadcrumb-pill-disabled"
                              data-bs-toggle="tooltip"
                              data-bs-placement="bottom"
                              title="<?= e(t('common.tooltip.path_unavailable')) ?>">
                            <?= e($crumb['label']) ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
