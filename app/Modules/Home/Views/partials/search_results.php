<?php
/**
 * HTMX partial: quick search dropdown results.
 * Variables: $q, $grouped
 */
/** Highlight search term in pre-escaped text. */
if (!function_exists('search_highlight')) {
    function search_highlight(string $text, string $query): string
    {
        $escaped = e($text);
        if ($query === '') {
            return $escaped;
        }
        return preg_replace(
            '/(' . preg_quote($query, '/') . ')/i',
            '<mark>$1</mark>',
            $escaped
        );
    }
}
?>
<?php if ($q === ''): ?>
    <!-- empty: no dropdown -->
<?php elseif (empty($grouped)): ?>
    <div class="dropdown-menu show w-100 p-3 text-center text-muted shadow-sm hm-search-dropdown hm-search-dropdown-empty">
        <small><?= t('home.search.no_results_for', ['query' => e($q)]) ?></small>
    </div>
<?php else: ?>
    <div class="dropdown-menu show w-100 shadow-sm hm-search-dropdown hm-search-dropdown-results">
        <?php foreach ($grouped as $group): ?>
            <div class="px-3 py-1 bg-light border-bottom">
                <small class="fw-semibold text-muted">
                    <i class="fa-solid <?= e($group['icon']) ?> me-1"></i>
                    <?= e($group['label']) ?>
                </small>
            </div>
            <?php foreach ($group['results'] as $result): ?>
                <a href="<?= e($result['url']) ?>" class="dropdown-item py-2 d-flex align-items-center gap-2">
                    <i class="fa-solid <?= e($result['icon'] ?? $group['icon']) ?> text-muted small"></i>
                    <div class="text-truncate">
                        <span class="fw-medium"><?= search_highlight($result['title'], $q) ?></span>
                        <?php if (!empty($result['badge'])): ?>
                            <span class="badge bg-warning text-dark ms-1 hm-search-badge"><?= e($result['badge']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($result['subtitle'])): ?>
                            <br><small class="text-muted"><?= search_highlight(mb_substr($result['subtitle'], 0, 80), $q) ?></small>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <div class="border-top px-3 py-2 text-center">
            <a href="<?= e(route('search.index')) ?>?q=<?= urlencode($q) ?>" class="small text-decoration-none">
                <i class="fa-solid fa-magnifying-glass me-1"></i> <?= e(t('home.search.view_all')) ?>
            </a>
        </div>
    </div>
<?php endif; ?>
