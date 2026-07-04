<?php if (empty($conversations)): ?>
    <div class="tm-conv-empty">
        <svg class="tm-empty-illustration" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="60" cy="60" r="50" fill="currentColor" opacity="0.08"/>
            <path d="M35 45 q0 -10 10 -10 h30 q10 0 10 10 v20 q0 10 -10 10 h-20 l-10 10 v-10 q-10 0 -10 -10 z" fill="currentColor" opacity="0.35"/>
            <circle cx="50" cy="55" r="2.5" fill="#fff"/>
            <circle cx="60" cy="55" r="2.5" fill="#fff"/>
            <circle cx="70" cy="55" r="2.5" fill="#fff"/>
        </svg>
        <p class="tm-empty-title"><?= e(t('teams.conv_list.no_conversations')) ?></p>
        <small class="tm-empty-subtitle"><?= e(t('teams.conv_list.subtitle')) ?></small>
        <?php if (!empty($searchQuery ?? null)): ?>
            <small class="text-muted mt-2 d-block"><?= e(t('teams.conv_list.no_results_for', ['query' => $searchQuery])) ?></small>
        <?php elseif (has_permission('teams.create')): ?>
            <button type="button" class="btn btn-sm btn-primary mt-3"
                    data-bs-toggle="modal" data-bs-target="#tm-new-conv-modal">
                <i class="fa-solid fa-plus me-1"></i><?= e(t('teams.conv_list.start_conversation_btn')) ?>
            </button>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php foreach ($conversations as $conv): ?>
        <?php $view->include('Teams/Views/partials/conversation_item', [
            'conv'       => $conv,
            'activeId'   => $activeId ?? null,
            'showHidden' => $showHidden ?? false,
        ]); ?>
    <?php endforeach; ?>
<?php endif; ?>
