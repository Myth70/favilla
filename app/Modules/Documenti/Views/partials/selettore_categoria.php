<?php
/**
 * Partial: selettore categoria come <select>.
 *
 * Variabili:
 *   $categorie (array tree), $selected, $fieldName, $fieldId, $required (bool),
 *   $cssClass, $searchable (bool, default true)
 */
$required   = $required   ?? false;
$cssClass   = $cssClass   ?? 'form-select';
$fieldName  = $fieldName  ?? 'categoria_id';
$fieldId    = $fieldId    ?? ('dc-cat-' . substr(md5($fieldName), 0, 6));
$selected   = (string) ($selected ?? '');
$searchable = $searchable ?? true;

if ($searchable && strpos($cssClass, 'dc-searchable') === false) {
    $cssClass .= ' dc-searchable';
}

if (!function_exists('dc_render_select_options')) {
    function dc_render_select_options(array $cats, string $selected, int $depth = 0): void
    {
        foreach ($cats as $cat) {
            $indent = str_repeat('— ', $depth);
            $sel = $selected === (string) $cat['id'] ? 'selected' : '';
            echo '<option value="' . (int) $cat['id'] . '" ' . $sel . '>'
               . e($indent . $cat['nome'])
               . '</option>';
            if (!empty($cat['children'])) {
                dc_render_select_options($cat['children'], $selected, $depth + 1);
            }
        }
    }
}
?>
<select name="<?= e($fieldName) ?>"
        id="<?= e($fieldId) ?>"
        class="<?= e(trim($cssClass)) ?>"
        data-placeholder="<?= e(t('documenti.selettore_categoria.cerca_placeholder')) ?>"
        <?= $required ? 'required' : '' ?>>
    <?php if (!$required): ?>
        <option value=""><?= e(t('documenti.selettore_categoria.nessuna_categoria')) ?></option>
    <?php else: ?>
        <option value="" disabled <?= $selected === '' ? 'selected' : '' ?>><?= e(t('documenti.selettore_categoria.seleziona_categoria')) ?></option>
    <?php endif; ?>
    <?php dc_render_select_options($categorie ?? [], $selected); ?>
</select>
