<?php
/**
 * Variante "quick action" del mute toggle per l'offcanvas Info gruppo.
 *
 * Render distinto da mute_button.php (che è la pillola del chat header 1:1):
 * qui è un bottone tondeggiante con icona + label dentro lo slot
 * .tm-gp-quick-slot. Il controller TeamsController::toggleMute distingue il
 * partial da renderizzare in base al parametro `variant=quick` passato via
 * hx-vals (vedi `hx-post` qui sotto).
 *
 * @var int  $convId
 * @var bool $isMuted
 */
?>
<button class="tm-gp-quick-btn"
        hx-post="<?= e(route('teams.conversations.mute', ['id' => $convId])) ?>"
        hx-target="#tm-mute-btn"
        hx-swap="innerHTML"
        hx-vals='{"variant":"quick"}'
        title="<?= $isMuted ? e(t('teams.group_panel.muted_click_to_unmute')) : e(t('teams.chat_panel.mute_notifications_tip')) ?>"
        type="button">
    <i class="fa-solid <?= $isMuted ? 'fa-bell-slash' : 'fa-bell' ?>"></i>
    <span class="tm-gp-quick-label"><?= $isMuted ? e(t('teams.group_panel.unmute_btn')) : e(t('teams.group_panel.mute_btn')) ?></span>
</button>
