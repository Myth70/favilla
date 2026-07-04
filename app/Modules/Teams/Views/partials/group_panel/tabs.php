<?php
/**
 * Navigazione tab Membri | Media | File | Link.
 *
 * Tab Membri è eager (lista già caricata in $members).
 * Gli altri tab sono lazy: il primo "shown.bs.tab" triggera l'HTMX load
 * (gestito da Teams.initGroupPanelTabs in teams.js).
 *
 * @var array $conv
 * @var array $members
 * @var int   $currentUserId
 * @var bool  $canManage
 */
$convId = (int) $conv['id'];
?>
<ul class="nav nav-tabs tm-gp-tabs" role="tablist" id="tm-gp-tablist">
    <li class="nav-item flex-fill" role="presentation">
        <button class="nav-link active w-100" id="tm-gp-tab-members-btn"
                data-bs-toggle="tab" data-bs-target="#tm-gp-tab-members"
                data-tab="members" type="button" role="tab"
                aria-controls="tm-gp-tab-members" aria-selected="true">
            <i class="fa-solid fa-users d-block d-md-inline me-md-1"></i>
            <span><?= e(t('teams.group_panel.tab_members')) ?></span>
        </button>
    </li>
    <li class="nav-item flex-fill" role="presentation">
        <button class="nav-link w-100" id="tm-gp-tab-media-btn"
                data-bs-toggle="tab" data-bs-target="#tm-gp-tab-media"
                data-tab="media" type="button" role="tab"
                aria-controls="tm-gp-tab-media" aria-selected="false">
            <i class="fa-regular fa-image d-block d-md-inline me-md-1"></i>
            <span><?= e(t('teams.group_panel.tab_media')) ?></span>
        </button>
    </li>
    <li class="nav-item flex-fill" role="presentation">
        <button class="nav-link w-100" id="tm-gp-tab-files-btn"
                data-bs-toggle="tab" data-bs-target="#tm-gp-tab-files"
                data-tab="files" type="button" role="tab"
                aria-controls="tm-gp-tab-files" aria-selected="false">
            <i class="fa-regular fa-file d-block d-md-inline me-md-1"></i>
            <span><?= e(t('teams.group_panel.tab_files')) ?></span>
        </button>
    </li>
    <li class="nav-item flex-fill" role="presentation">
        <button class="nav-link w-100" id="tm-gp-tab-links-btn"
                data-bs-toggle="tab" data-bs-target="#tm-gp-tab-links"
                data-tab="links" type="button" role="tab"
                aria-controls="tm-gp-tab-links" aria-selected="false">
            <i class="fa-solid fa-link d-block d-md-inline me-md-1"></i>
            <span><?= e(t('teams.group_panel.tab_links')) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content tm-gp-tab-content">
    <div class="tab-pane fade show active" id="tm-gp-tab-members" role="tabpanel" aria-labelledby="tm-gp-tab-members-btn">
        <?php $view->include('Teams/Views/partials/group_panel/tab_members', [
            'conv'          => $conv,
            'members'       => $members,
            'currentUserId' => $currentUserId,
            'canManage'     => $canManage,
        ]); ?>
    </div>

    <div class="tab-pane fade" id="tm-gp-tab-media" role="tabpanel" aria-labelledby="tm-gp-tab-media-btn"
         data-lazy-url="<?= e(route('teams.panel.media', ['id' => $convId])) ?>">
        <div class="tm-gp-tab-placeholder text-center py-4 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span><?= e(t('teams.index.loading')) ?>
        </div>
    </div>

    <div class="tab-pane fade" id="tm-gp-tab-files" role="tabpanel" aria-labelledby="tm-gp-tab-files-btn"
         data-lazy-url="<?= e(route('teams.panel.files', ['id' => $convId])) ?>">
        <div class="tm-gp-tab-placeholder text-center py-4 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span><?= e(t('teams.index.loading')) ?>
        </div>
    </div>

    <div class="tab-pane fade" id="tm-gp-tab-links" role="tabpanel" aria-labelledby="tm-gp-tab-links-btn"
         data-lazy-url="<?= e(route('teams.panel.links', ['id' => $convId])) ?>">
        <div class="tm-gp-tab-placeholder text-center py-4 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span><?= e(t('teams.index.loading')) ?>
        </div>
    </div>
</div>
