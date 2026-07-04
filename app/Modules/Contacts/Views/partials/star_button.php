<?php
/**
 * Partial: pulsante stella preferito (toggle HTMX)
 * Variabili: $id, $preferito
 */
?>
<button class="ct-star-btn <?= $preferito ? 'active' : '' ?>"
        hx-post="<?= e(route('contacts.toggle-preferito', ['id' => $id])) ?>"
        hx-target="this" hx-swap="outerHTML"
        hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'
        title="<?= $preferito ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti' ?>">
  <i class="fa-<?= $preferito ? 'solid' : 'regular' ?> fa-star"></i>
</button>
