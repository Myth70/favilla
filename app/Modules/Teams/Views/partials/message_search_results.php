<?php
/** @var array<int,array> $results */
/** @var string $q */
/** @var int $conversationId */
?>

<?php if (trim($q) === ''): ?>
    <div class="text-muted small p-2"><?= e(t('teams.message_search.enter_query')) ?></div>
<?php elseif (empty($results)): ?>
    <div class="text-muted small p-2"><?= e(t('teams.message_search.no_results')) ?></div>
<?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($results as $msg): ?>
            <a class="list-group-item list-group-item-action"
               href="<?= e(route('teams.show', ['id' => $conversationId])) ?>#msg-<?= (int) ($msg['id'] ?? 0) ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-semibold"><?= e($msg['user_name'] ?? t('teams.exception.default_user_name')) ?></div>
                        <div class="small text-muted"><?= e(mb_substr((string) ($msg['body'] ?? ''), 0, 180)) ?></div>
                    </div>
                    <small class="text-muted"><?= e(format_date($msg['created_at'] ?? null, 'compact')) ?></small>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
