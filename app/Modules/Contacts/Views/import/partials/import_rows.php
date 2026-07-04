<?php
/**
 * Partial HTMX: tbody con le righe dei record importabili.
 * Variabili: $module, $source, $rows, $total, $page, $perPage, $filters
 */
?>
<?php if (empty($rows)): ?>
  <tr>
    <td colspan="4" class="text-center text-muted py-4">
      <i class="fa-solid fa-inbox me-2" aria-hidden="true"></i>
      <?= !empty($filters['q'])
            ? 'Nessun record corrisponde alla ricerca.'
            : 'Nessun record disponibile in questa sorgente.' ?>
    </td>
  </tr>
<?php else: ?>
  <?php foreach ($rows as $row):
    $sourceId = $row['source_id'] ?? null;
    $nomeFull = trim(($row['nome'] ?? '') . ' ' . ($row['cognome'] ?? ''));
    $preview  = $row['preview']
                ?? trim($nomeFull . (!empty($row['azienda']) ? ' — ' . $row['azienda'] : ''));
  ?>
    <tr>
      <td>
        <div class="fw-semibold"><?= e($preview ?: ('Record #' . $sourceId)) ?></div>
        <?php if (!empty($row['ruolo']) || !empty($row['azienda'])): ?>
          <div class="small text-muted">
            <?= e(trim(($row['ruolo'] ?? '') . (!empty($row['ruolo']) && !empty($row['azienda']) ? ' · ' : '') . ($row['azienda'] ?? ''))) ?>
          </div>
        <?php endif; ?>
      </td>
      <td class="d-none d-md-table-cell text-muted small">
        <?= !empty($row['email']) ? e($row['email']) : '<span class="text-muted">—</span>' ?>
      </td>
      <td class="d-none d-md-table-cell text-muted small">
        <?= !empty($row['telefono']) ? e($row['telefono']) : '<span class="text-muted">—</span>' ?>
      </td>
      <td class="text-end">
        <?php if ($sourceId !== null): ?>
          <a href="<?= e(route('contacts.import.preview', ['module' => $module, 'source' => $source, 'sourceId' => $sourceId])) ?>"
             class="btn btn-sm btn-primary"
             data-bs-toggle="tooltip" title="<?= e(t('contacts.import.preview_tip')) ?>">
            <i class="fa-solid fa-file-import me-1"></i> Importa
          </a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
<?php endif; ?>
