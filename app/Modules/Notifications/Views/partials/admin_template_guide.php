<div class="card shadow-sm mt-4 ntas-template-guide">
    <div class="card-header">
        <i class="fa-solid fa-book-open me-2"></i><?= e(t('notifications.admin.guide_title')) ?>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            <?= e(t('notifications.admin.guide_intro')) ?>
        </p>

        <div class="row g-3">
            <div class="col-lg-6">
                <h6 class="mb-2"><?= e(t('notifications.admin.guide_globals')) ?></h6>
                <ul class="ntas-guide-list mb-0">
                    <li><code>{{title}}</code>, <code>{{body}}</code>, <code>{{type}}</code>, <code>{{link}}</code></li>
                    <li><code>{{date}}</code>, <code>{{time}}</code>, <code>{{datetime}}</code>, <code>{{date_it}}</code>, <code>{{time_it}}</code></li>
                    <li><code>{{recipient_user_name}}</code>, <code>{{recipient_user_id}}</code>, <code>{{sender_user_name}}</code></li>
                    <li><code>{{module_slug}}</code>, <code>{{event_slug}}</code>, <code>{{channel_slug}}</code></li>
                </ul>
            </div>
            <div class="col-lg-6">
                <h6 class="mb-2"><?= e(t('notifications.admin.guide_best')) ?></h6>
                <ul class="ntas-guide-list mb-0">
                    <li><?= e(t('notifications.admin.guide_best_1')) ?></li>
                    <li><?= e(t('notifications.admin.guide_best_2')) ?></li>
                    <li><?= e(t('notifications.admin.guide_best_3')) ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
