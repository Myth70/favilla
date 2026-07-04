<?php if ($hasOlderMessages && !empty($messages)): ?>
<div class="tm-load-older"
     id="tm-load-older"
     hx-get="<?= e(route('teams.messages.index', ['id' => $conversationId])) ?>?before=<?= (int) $messages[0]['id'] ?>"
     hx-trigger="revealed"
     hx-target="#tm-load-older"
     hx-swap="outerHTML">
    <div class="text-center py-2">
        <span class="spinner-border spinner-border-sm text-primary"></span>
        <small class="text-muted ms-1"><?= e(t('teams.index.loading')) ?></small>
    </div>
</div>
<?php endif; ?>

<?php
$prevDate    = '';
$prevUserId  = null;
$prevMsgTime = null;
$_msgRepo    = app(\App\Modules\Teams\Repositories\MessageRepository::class);
$_msgIds     = array_column($messages, 'id');
$_reactionsMap = !empty($_msgIds) ? $_msgRepo->getReactionsForMessages($_msgIds) : [];
foreach ($messages as $msg):
    $msgDate      = date('Y-m-d', strtotime($msg['created_at']));
    $msgTimestamp = strtotime($msg['created_at']);
    $isDateChange = $msgDate !== $prevDate;
    if ($isDateChange):
        $prevDate = $msgDate;
        ?>
    <div class="tm-date-divider">
        <span><?= e(format_date($msg['created_at'], 'relative')) ?></span>
    </div>
<?php
    endif;
    $isSystemMsg = ($msg['type'] ?? 'text') === 'system';
    $isConsecutive = !$isDateChange
        && !$isSystemMsg
        && $prevUserId === (int) $msg['user_id']
        && $prevMsgTime !== null
        && ($msgTimestamp - $prevMsgTime) < 300;
    $view->include('Teams/Views/partials/message_bubble', [
        'msg'              => $msg,
        'currentUserId'    => $currentUserId,
        'showAvatar'       => !$isConsecutive,
        'isConsecutive'    => $isConsecutive,
        'othersMaxReadAt'  => $othersMaxReadAt ?? null,
        'reactions'        => $_reactionsMap[(int) $msg['id']] ?? [],
    ]);
    $prevUserId  = (int) $msg['user_id'];
    $prevMsgTime = $msgTimestamp;
endforeach;
?>
