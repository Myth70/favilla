<?php
/**
 * Campanella notifiche nell'header.
 * Nota: il <link> è emesso direttamente perché questo partial viene incluso
 * dal layout (header.php) DOPO che il <head> è già stato reso — pushStyle()
 * non funzionerebbe in questo contesto.
 */
$view->pushScript('js/nt-notifications.js');
?>
<link rel="stylesheet" href="<?= e(asset('css/nt-notifications.css')) ?>">
<div class="dropdown" id="nt-bell-wrapper">
    <button class="btn btn-link text-body p-0 nt-bell-btn position-relative"
            type="button"
            id="nt-bell-btn"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-expanded="false"
            title="<?= e(t('notifications.bell.title')) ?>">
        <i class="fa-solid fa-bell fa-lg"></i>
        <!-- Badge: inizializzato lato PHP, aggiornato da HTMX ogni 60s.
             NB: hx-trigger NON include "load" per evitare il loop infinito
             causato da outerHTML swap che reinserisce il nodo nel DOM. -->
        <?php
        $ntInitCount  = \App\Modules\Notifications\Services\NotificationService::getUnreadCount(
            (int) ($_SESSION['user_id'] ?? 0)
        );
        $ntHiddenClass = $ntInitCount === 0 ? ' d-none' : '';
        ?>
        <span class="nt-badge badge rounded-pill bg-danger<?= $ntHiddenClass ?>"
              id="nt-badge-count"
              hx-get="<?= e(route('notifications.unread-count')) ?>"
              hx-trigger="every 60s, notifCountUpdated from:body, notifAllRead from:body"
              hx-target="#nt-badge-count"
              hx-swap="outerHTML"><?= $ntInitCount ?></span>
    </button>

    <div class="dropdown-menu dropdown-menu-end nt-dropdown p-0"
         id="nt-dropdown-menu"
         aria-labelledby="nt-bell-btn">
        <!-- Header statico — mai sostituito da HTMX -->
        <div class="nt-dropdown-header">
            <span><i class="fa-solid fa-bell me-1"></i> <?= e(t('notifications.dropdown.header')) ?></span>
        </div>
        <!-- Contenuto dinamico: caricato via HTMX all'apertura -->
        <div id="nt-dropdown-content"
             hx-get="<?= e(route('notifications.dropdown')) ?>"
             hx-trigger="show.bs.dropdown from:#nt-bell-btn"
             hx-target="#nt-dropdown-content"
             hx-swap="innerHTML">
            <div class="nt-empty">
                <i class="fa-regular fa-bell-slash"></i>
                <?= e(t('notifications.bell.open_to_view')) ?>
            </div>
        </div>
    </div>
</div>

