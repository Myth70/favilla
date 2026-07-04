<?php
/**
 * Filtri elenco documenti.
 * Variabili: $filters (array), $categorie (array tree)
 *           $action (string, default route documenti.index)
 *           $statiAttivi (?array<string>) — stati attualmente selezionati
 *           $showStato (bool, default true)
 */
use App\Modules\Documenti\Helpers\StatoHelper;

$action      = $action      ?? route('documenti.index');
$showStato   = $showStato   ?? true;
$statiAttivi = $statiAttivi ?? (array) ($filters['stato'] ?? []);
$hasActive   = !empty($filters['q'])
            || !empty($filters['categoria_id'])
            || !empty($filters['scadenza'])
            || !empty($statiAttivi);
?>
<form method="get" action="<?= e($action) ?>" class="card mb-0 dc-filters">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-lg-4">
                <label class="form-label small mb-1" for="dc-filter-q"><?= e(t('documenti.filtri.cerca_label')) ?></label>
                <input type="text" name="q" id="dc-filter-q" class="form-control form-control-sm"
                    placeholder="<?= e(t('documenti.filtri.cerca_placeholder')) ?>"
                    value="<?= e($filters['q'] ?? '') ?>"
                    autocomplete="off">
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label small mb-1" for="dc-filter-cat"><?= e(t('documenti.create.categoria_label')) ?></label>
                <select name="categoria_id" id="dc-filter-cat" class="form-select form-select-sm dc-searchable" data-placeholder="<?= e(t('documenti.filtri.tutte_categorie')) ?>">
                    <option value=""><?= e(t('documenti.filtri.tutte_categorie')) ?></option>
                    <?php
                    $renderCatOption = function (array $cats, int $depth = 0) use (&$renderCatOption, $filters) {
                        foreach ($cats as $cat) {
                            $indent = str_repeat('— ', $depth);
                            $sel = (string)($filters['categoria_id'] ?? '') === (string)$cat['id'] ? 'selected' : '';
                            echo '<option value="' . (int)$cat['id'] . '" ' . $sel . '>' . e($indent . $cat['nome']) . '</option>';
                            if (!empty($cat['children'])) {
                                $renderCatOption($cat['children'], $depth + 1);
                            }
                        }
                    };
$renderCatOption($categorie ?? []);
?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small mb-1" for="dc-filter-scad"><?= e(t('documenti.create.scadenza_label')) ?></label>
                <select name="scadenza" id="dc-filter-scad" class="form-select form-select-sm">
                    <option value=""><?= e(t('documenti.filtri.tutte')) ?></option>
                    <option value="prossimi_7"  <?= ($filters['scadenza'] ?? '') === 'prossimi_7' ? 'selected' : '' ?>><?= e(t('documenti.filtri.entro_7')) ?></option>
                    <option value="prossimi_30" <?= ($filters['scadenza'] ?? '') === 'prossimi_30' ? 'selected' : '' ?>><?= e(t('documenti.filtri.entro_30')) ?></option>
                    <option value="scaduti"     <?= ($filters['scadenza'] ?? '') === 'scaduti' ? 'selected' : '' ?>><?= e(t('documenti.filtri.gia_scaduti')) ?></option>
                </select>
            </div>
            <?php if ($showStato): ?>
            <div class="col-6 col-lg-3">
                <label class="form-label small mb-1" for="dc-filter-stato"><?= e(t('documenti.widget.col_stato')) ?></label>
                <select name="stato[]" id="dc-filter-stato" class="form-select form-select-sm" multiple size="1">
                    <?php foreach (StatoHelper::STATI as $code => $info): ?>
                        <option value="<?= e($code) ?>" <?= in_array($code, $statiAttivi, true) ? 'selected' : '' ?>>
                            <?= e(StatoHelper::label($code)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-12 col-lg-auto d-flex gap-2 mt-2 mt-lg-0">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1 flex-lg-grow-0">
                    <i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i><?= e(t('documenti.filtri.filtra_btn')) ?>
                </button>
                <?php if ($hasActive): ?>
                <a href="<?= e($action) ?>" class="btn btn-outline-secondary btn-sm flex-grow-1 flex-lg-grow-0"
                   title="<?= e(t('documenti.filtri.pulisci_tip')) ?>">
                    <i class="fa-solid fa-eraser me-1" aria-hidden="true"></i><?= e(t('documenti.filtri.pulisci_btn')) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>
