<?php
/**
 * Tab Membri: toolbar (filter input client-side + add-member collapse) +
 * wrapper #tm-member-list (target HTMX di members.store/destroy).
 *
 * Filtro client-side wirato in Teams.initMemberFilter (teams.js):
 *   on input → nasconde .tm-gp-member-row il cui data-name/data-email
 *   non matcha la query (case-insensitive).
 *
 * @var array $conv
 * @var array $members
 * @var int   $currentUserId
 * @var bool  $canManage
 */
?>
<div class="tm-gp-members">
    <div class="tm-gp-member-toolbar">
        <div class="position-relative flex-grow-1">
            <i class="fa-solid fa-magnifying-glass tm-gp-member-filter-icon"></i>
            <input type="text"
                   class="form-control form-control-sm tm-gp-member-filter-input"
                   id="tm-gp-member-filter"
                   placeholder="<?= e(t('teams.group_panel.filter_members_placeholder')) ?>"
                   autocomplete="off">
        </div>
        <?php if ($canManage): ?>
        <button type="button" class="btn btn-sm btn-primary"
                data-bs-toggle="collapse" data-bs-target="#tm-gp-add-member-collapse"
                aria-expanded="false" aria-controls="tm-gp-add-member-collapse"
                title="<?= e(t('teams.group_panel.add_member_tip')) ?>">
            <i class="fa-solid fa-user-plus"></i>
        </button>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
    <div class="collapse tm-gp-add-member-collapse" id="tm-gp-add-member-collapse">
        <div class="tm-gp-add-member-inner">
            <input type="text" class="form-control form-control-sm mb-2" placeholder="<?= e(t('teams.index.search_users_placeholder')) ?>"
                   hx-get="<?= e(route('teams.users.search')) ?>?exclude_conversation=<?= (int) $conv['id'] ?>"
                   hx-trigger="keyup changed delay:300ms"
                   hx-target="#tm-add-member-results"
                   name="q" autocomplete="off">
            <div id="tm-add-member-results"></div>
        </div>
    </div>
    <?php endif; ?>

    <div id="tm-member-list" class="tm-gp-member-list">
        <?php $view->include('Teams/Views/partials/member_list', [
            'conv'          => $conv,
            'members'       => $members,
            'currentUserId' => $currentUserId,
        ]); ?>
    </div>
</div>
