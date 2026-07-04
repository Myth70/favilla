<?php
/**
 * Barra reazioni emoji per un messaggio.
 * Variabili: $messageId, $conversationId, $reactions, $currentUserId, $allowedEmoji
 * hx-target: #tm-rx-{messageId} (wrapper che include sia la barra sia il picker)
 */
$allowedEmoji = $allowedEmoji ?? \App\Modules\Teams\Controllers\ReactionController::allowedEmoji();
$wrapperId    = 'tm-rx-' . (int) $messageId;
$reactionsUrl = route('teams.messages.reactions.toggle', [
    'id'        => $conversationId,
    'messageId' => $messageId,
]);
?>
<div id="<?= e($wrapperId) ?>"><?php
    /* La barra contiene solo le pill esistenti. Il bottone "Aggiungi reazione"
       è ora una voce del dropdown del meta (vedi message_bubble.php).
       Quando non ci sono reazioni omettiamo del tutto .tm-reactions per non
       riservare spazio (whitespace PHP impedirebbe :empty di scattare). */
?>
<?php if (!empty($reactions)): ?>
    <div class="tm-reactions">
        <?php foreach ($reactions as $r): ?>
        <?php $reacted = in_array($currentUserId, $r['user_ids'], true); ?>
        <button type="button"
                class="tm-reaction-pill <?= $reacted ? 'tm-reaction-mine' : '' ?>"
                hx-post="<?= e($reactionsUrl) ?>"
                hx-vals='{"emoji": "<?= e($r['emoji']) ?>"}'
                hx-target="#<?= e($wrapperId) ?>"
                hx-swap="outerHTML"
                title="<?= $reacted ? e(t('teams.reactions.remove_tip')) : e(t('teams.reactions.add_tip')) ?>"><?= e($r['emoji']) ?> <span class="tm-reaction-count"><?= (int) $r['count'] ?></span></button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

    <!-- Emoji picker (hidden, mostrato via JS aprendo "Aggiungi reazione" dal dropdown del meta) -->
    <div class="tm-emoji-picker d-none" id="tm-picker-<?= (int) $messageId ?>">
        <?php foreach ($allowedEmoji as $emoji): ?>
        <button type="button"
                class="tm-emoji-btn"
                hx-post="<?= e($reactionsUrl) ?>"
                hx-vals='{"emoji": "<?= e($emoji) ?>"}'
                hx-target="#<?= e($wrapperId) ?>"
                hx-swap="outerHTML">
            <?= e($emoji) ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>
