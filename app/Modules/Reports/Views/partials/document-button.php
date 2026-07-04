<?php
/**
 * Reusable document generation button — include from any module view.
 *
 * Variables:
 *   $docModule    string  Module name (e.g. 'Clienti')
 *   $docOperation string  Operation key (e.g. 'invoice')
 *   $docRecordId  int     Record primary key
 *   $docLabel     string  Optional button label (default 'Genera PDF')
 */
if (!isModuleEnabled('Reports')) {
    return;
}
$_docLabel = $docLabel ?? t('reports.document_btn.default');
?>
<a href="<?= e(route('reports.documents.generate', [
    'module'    => $docModule,
    'operation' => $docOperation,
    'recordId'  => $docRecordId,
])) ?>"
   class="btn btn-sm btn-outline-danger"
   target="_blank"
   title="<?= e($_docLabel) ?>">
    <i class="fa-solid fa-file-pdf me-1"></i><?= e($_docLabel) ?>
</a>
