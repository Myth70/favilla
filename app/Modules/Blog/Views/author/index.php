<?php
/**
 * Author's article dashboard with hero profile.
 * Variables: $view, $items, $total, $page, $per_page, $total_pages, $filters, $stats, $authorProfile
 */
$view->layout('main');
$view->pushStyle('css/blog.css');
$view->start('content');

use App\Modules\Auth\Helpers\AvatarHelper;

$stats   = $stats ?? ['total_articles' => 0, 'published' => 0, 'drafts' => 0, 'total_views' => 0, 'total_likes' => 0, 'total_comments' => 0];
$profile = $authorProfile ?? [];

$avatarUrl = AvatarHelper::url($profile['avatar'] ?? null);
$initials  = AvatarHelper::initials($profile['name'] ?? 'U');

$heroStats = [
    ['value' => (int) ($stats['total_articles'] ?? 0), 'label' => t('blog.author.stats.articles'),   'icon' => 'fa-solid fa-newspaper',    'color' => 'primary'],
    ['value' => (int) ($stats['published'] ?? 0),      'label' => t('blog.author.stats.published'), 'icon' => 'fa-solid fa-circle-check', 'color' => 'success'],
    ['value' => (int) ($stats['drafts'] ?? 0),         'label' => t('blog.author.stats.drafts'),      'icon' => 'fa-solid fa-file-pen',     'color' => 'secondary'],
    ['value' => (int) ($stats['total_views'] ?? 0),    'label' => t('blog.author.stats.views'),     'icon' => 'fa-regular fa-eye',         'color' => 'info'],
    ['value' => (int) ($stats['total_likes'] ?? 0),    'label' => t('blog.author.stats.likes'),       'icon' => 'fa-regular fa-heart',       'color' => 'danger'],
    ['value' => (int) ($stats['total_comments'] ?? 0), 'label' => t('blog.author.stats.comments'),   'icon' => 'fa-regular fa-comment',     'color' => 'primary'],
];
?>

<div class="container-fluid">
<div class="row g-4">

    <!-- Hero card — author profile with stats -->
    <div class="col-12">
        <?php
        $blogButtons = '<a href="' . e(route('blog.create')) . '" class="btn btn-primary btn-sm text-nowrap">' .
                       '<i class="fa-solid fa-plus me-1"></i>' . e(t('blog.author.new_article')) . '</a>' .
                       '<a href="' . e(route('blog.saved')) . '" class="btn btn-outline-warning btn-sm text-nowrap">' .
                       '<i class="fa-solid fa-bookmark me-1"></i>' . e(t('blog.author.saved_button')) . '</a>';
        $view->include('partials/pf-hero-user', [
            'userName'    => $profile['name'] ?? t('blog.public.author.fallback'),
            'userSubtitle' => $profile['email'] ?? '',
            'userAvatar'  => $avatarUrl ?? null,
            'userInitials' => $initials,
            'userStats'   => $heroStats,
            'userButtons' => $blogButtons,
        ]);
        ?>
    </div>

    <!-- Filters -->
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-body">
                <form hx-get="<?= e(route('blog.author.index')) ?>"
                      hx-target="#author-table"
                      hx-push-url="true"
                      hx-trigger="change from:select, submit"
                      class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-1"><?= e(t('blog.author.search_label')) ?></label>
                        <input type="text" name="search" value="<?= e($filters['search'] ?? '') ?>"
                               class="form-control" placeholder="<?= e(t('blog.author.search_placeholder')) ?>"
                               hx-trigger="keyup changed delay:400ms"
                               hx-get="<?= e(route('blog.author.index')) ?>"
                               hx-target="#author-table"
                               hx-push-url="true"
                               hx-include="[name='status']">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1"><?= e(t('blog.author.status_label')) ?></label>
                        <select name="status" class="form-select">
                            <option value=""><?= e(t('blog.author.status_all')) ?></option>
                            <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>><?= e(t('blog.status.draft')) ?></option>
                            <option value="published" <?= ($filters['status'] ?? '') === 'published' ? 'selected' : '' ?>><?= e(t('blog.status.published')) ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= e(route('blog.author.index')) ?>" class="btn btn-outline-secondary w-100">
                            <i class="fa-solid fa-xmark me-1"></i> <?= e(t('blog.author.reset')) ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12" id="author-table">
        <?php $view->include('Blog/Views/author/partials/table', compact('items', 'total', 'page', 'per_page', 'total_pages', 'filters')); ?>
    </div>

</div>
</div>

<?php $view->end(); ?>
