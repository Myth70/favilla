<button class="btn btn-sm btn-link text-body"
        hx-post="<?= e(route('teams.conversations.mute', ['id' => $convId])) ?>"
        hx-target="#tm-mute-btn"
        hx-swap="outerHTML"
        title="<?= e($title) ?>"
        id="tm-mute-btn">
    <i class="fa-solid <?= e($icon) ?>"></i>
</button>
