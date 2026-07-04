<?php
$feedbackMap = [
    'pending' => ['label' => t('helponline.admin.fb_pending'), 'class' => 'secondary'],
    '1' => ['label' => t('helponline.admin.fb_helpful'), 'class' => 'success'],
    '0' => ['label' => t('helponline.admin.fb_unhelpful'), 'class' => 'danger'],
];
?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead>
        <tr>
            <th><?= e(t('helponline.admin.col_question')) ?></th>
            <th><?= e(t('helponline.admin.col_context')) ?></th>
            <th><?= e(t('helponline.admin.col_response')) ?></th>
            <th><?= e(t('helponline.admin.col_confidence')) ?></th>
            <th><?= e(t('helponline.admin.col_feedback')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($queries)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?= e(t('helponline.admin.no_queries')) ?></td></tr>
        <?php else: ?>
            <?php foreach ($queries as $queryRow): ?>
                <?php
                $feedbackKey = array_key_exists('helpful', $queryRow) && $queryRow['helpful'] !== null
                    ? (string) $queryRow['helpful']
                    : 'pending';
                $feedback = $feedbackMap[$feedbackKey] ?? $feedbackMap['pending'];
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e((string) $queryRow['query_text']) ?></div>
                        <div class="small text-muted"><?= e((string) ($queryRow['user_name'] ?? t('helponline.admin.anon_user'))) ?> · <?= e((string) ($queryRow['created_at'] ?? '')) ?></div>
                    </td>
                    <td><?= e((string) ($queryRow['context_module'] ?? t('helponline.admin.context_general'))) ?></td>
                    <td>
                        <div><?= e((string) ($queryRow['response_title'] ?? t('helponline.admin.no_match'))) ?></div>
                        <?php if (!empty($queryRow['entry_title'])): ?>
                            <div class="small text-muted"><?= e((string) $queryRow['entry_title']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) ($queryRow['confidence'] ?? 0) ?>%</td>
                    <td><span class="badge text-bg-<?= e($feedback['class']) ?>"><?= e($feedback['label']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>