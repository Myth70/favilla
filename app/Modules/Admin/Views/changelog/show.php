<?php
/**
 * Changelog — dettaglio release.
 * Variables: $release
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->start('content');

$headerActions = '<span id="ch-badge-' . (int) $release['id'] . '" class="me-2">';
ob_start();
$view->include('Admin/Views/changelog/partials/publish-badge', ['release' => $release]);
$headerActions .= ob_get_clean();
$headerActions .= '</span>';
$headerActions .= '<a href="' . e(route('admin.changelog.edit', ['id' => $release['id']])) . '" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="' . e(t('admin.changelog.edit_tip')) . '"><i class="fa-solid fa-pen-to-square me-1"></i>' . e(t('admin.changelog.edit_btn')) . '</a>';
$headerActions .= '<form method="POST" action="' . e(route('admin.changelog.destroy', ['id' => $release['id']])) . '" class="d-inline">';
$headerActions .= csrf_field();
$headerActions .= '<input type="hidden" name="_method" value="DELETE">';
$headerActions .= '<button type="submit" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="' . e(t('admin.changelog.delete_tip')) . '" data-app-confirm="' . e(t('admin.changelog.delete_confirm', ['version' => $release['version']])) . '"><i class="fa-solid fa-trash me-1"></i>' . e(t('admin.changelog.delete_btn')) . '</button></form>';

$subtitle = '<span class="adm-version-pill me-2">v' . e($release['version']) . '</span>';
$subtitle .= '<i class="fa-regular fa-calendar me-1"></i>' . e(date('d/m/Y', strtotime($release['release_date'])));
if ($release['author_name'] ?? null) {
    $subtitle .= ' · <i class="fa-regular fa-user me-1"></i>' . e($release['author_name']);
}
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid fa-code-branch',
        'adminTitle'    => $release['title'],
        'adminSubtitle' => $subtitle,
        'adminButtons'  => $headerActions,
    ]); ?>

<div class="row justify-content-center">
<div class="col-lg-8">

<!-- Note di release -->
<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="fa-solid fa-file-lines me-2"></i><?= e(t('admin.changelog.notes_heading')) ?></h6>
    </div>
    <div class="card-body">
        <pre class="adm-notes-pre"><?= e($release['notes']) ?></pre>
    </div>
</div>

<div class="mt-3">
    <a href="<?= e(route('admin.changelog.index')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('admin.changelog.back')) ?>
    </a>
</div>

</div>
</div>
</div>

<?php $view->end(); ?>
