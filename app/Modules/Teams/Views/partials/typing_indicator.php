<?php
// NB: i nomi restano grezzi (non escapati) fino all'output finale — la
// sostituzione di Translator::interpolate() è un semplice strtr() senza
// escaping, quindi `e()` va applicato UNA SOLA VOLTA sul testo tradotto
// completo, non sui singoli nomi prima dell'interpolazione (altrimenti
// basterebbe rimuovere l'e() qui sopra in un refactor futuro per riaprire
// una XSS sui nomi utente mostrati nel typing indicator).
$typingUsers = $typingUsers ?? [];
$names = array_map(function ($u) {
    return (string) ($u['name'] ?? '');
}, $typingUsers);
$count = count($names);
$text = '';
if ($count === 1) {
    $text = t('teams.typing.one', ['name' => $names[0]]);
} elseif ($count === 2) {
    $text = t('teams.typing.two', ['name1' => $names[0], 'name2' => $names[1]]);
} elseif ($count > 2) {
    $text = t('teams.typing.many', ['name' => $names[0], 'count' => $count - 1]);
}
$isOob = !empty($oob);
?>
<?php if ($isOob): ?>
<div id="tm-typing-indicator" hx-swap-oob="innerHTML">
<?php endif; ?>
<?php if ($text !== ''): ?>
<div class="tm-typing">
    <span class="tm-typing-dots">
        <span class="tm-typing-dot"></span>
        <span class="tm-typing-dot"></span>
        <span class="tm-typing-dot"></span>
    </span>
    <small class="text-muted ms-1"><?= e($text) ?></small>
</div>
<?php endif; ?>
<?php if ($isOob): ?>
</div>
<?php endif; ?>
