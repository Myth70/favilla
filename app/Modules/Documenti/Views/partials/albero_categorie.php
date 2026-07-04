<?php

/**
 * Partial: albero categorie ricorsivo con expand/collapse.
 *
 * Variabili:
 *   $categorie  array tree con chiave 'children'
 *   $readOnly   bool (default false) — nasconde le azioni di gestione
 */
$readOnly = $readOnly ?? false;

if (!function_exists('dc_render_tree')) {
    function dc_render_tree(array $cats, bool $readOnly, int $depth = 0): void
    {
        if (empty($cats)) {
            return;
        }
        echo '<ul class="dc-tree' . ($depth === 0 ? '' : '') . '">';
        foreach ($cats as $cat) {
            $hasChildren = !empty($cat['children']);
            $count = (int) ($cat['n_documenti'] ?? 0);
            $canDelete = empty($cat['children']) && $count === 0;

            echo '<li>';
            if ($hasChildren) {
                echo '<details open>';
                echo '<summary>';
            }
            echo '<div class="dc-tree-node d-flex justify-content-between align-items-center gap-2 flex-wrap">';

            echo '<div class="d-flex align-items-center min-w-0 flex-grow-1">';
            if ($hasChildren) {
                echo '<span class="dc-tree-toggle" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>';
            } else {
                echo '<span class="dc-tree-leaf" aria-hidden="true"><i class="fa-solid fa-circle" style="font-size:.35rem;vertical-align:middle"></i></span>';
            }
            echo '<i class="fa-solid ' . ($hasChildren ? 'fa-folder-open' : 'fa-folder') . ' text-warning ms-1 me-2" aria-hidden="true"></i>';
            echo '<span class="text-truncate">';
            echo '<strong>' . e($cat['nome']) . '</strong>';
            echo ' <code class="dc-code">' . e($cat['codice']) . '</code>';
            if ($count > 0) {
                echo '<span class="dc-tree-count">(' . e(tc('documenti.albero.n_documenti', $count)) . ')</span>';
            }
            if (!empty($cat['descrizione'])) {
                echo '<small class="text-muted ms-2 d-none d-md-inline">' . e($cat['descrizione']) . '</small>';
            }
            echo '</span>';
            echo '</div>';

            if (!$readOnly && has_permission('documenti.manage_categorie')) {
                echo '<span class="d-flex gap-1 flex-shrink-0">';
                echo '<button type="button" class="btn btn-sm btn-outline-secondary"'
                   . ' data-bs-toggle="modal" data-bs-target="#modalSpostaCategoria"'
                   . ' data-cat-id="' . (int) $cat['id'] . '"'
                   . ' data-cat-nome="' . e($cat['nome']) . '"'
                   . ' aria-label="' . e(t('documenti.albero.sposta_categoria', ['nome' => $cat['nome']])) . '"'
                   . ' title="' . e(t('documenti.categorie.sposta_title')) . '">'
                   . '<i class="fa-solid fa-arrows-up-down-left-right" aria-hidden="true"></i></button>';

                if ($canDelete) {
                    echo '<form method="post" action="' . e(route('documenti.categorie.destroy', ['id' => $cat['id']])) . '" class="d-inline"'
                       . ' data-app-confirm="' . e(t('documenti.albero.elimina_confirm', ['nome' => $cat['nome']])) . '">';
                    echo csrf_field();
                    echo '<input type="hidden" name="_method" value="DELETE">';
                    echo '<button type="submit" class="btn btn-sm btn-outline-danger"'
                       . ' aria-label="' . e(t('documenti.albero.elimina_categoria', ['nome' => $cat['nome']])) . '" title="' . e(t('documenti.albero.elimina_categoria_tip')) . '">'
                       . '<i class="fa-solid fa-trash" aria-hidden="true"></i></button></form>';
                } else {
                    echo '<button type="button" class="btn btn-sm btn-outline-danger" disabled'
                       . ' data-bs-toggle="tooltip"'
                       . ' aria-label="' . e(t('documenti.albero.non_eliminabile')) . '"'
                       . ' title="' . e(t('documenti.albero.non_eliminabile_help')) . '">'
                       . '<i class="fa-solid fa-trash" aria-hidden="true"></i></button>';
                }
                echo '</span>';
            }

            echo '</div>';
            if ($hasChildren) {
                echo '</summary>';
                dc_render_tree($cat['children'], $readOnly, $depth + 1);
                echo '</details>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }
}

dc_render_tree($categorie ?? [], (bool) $readOnly);
