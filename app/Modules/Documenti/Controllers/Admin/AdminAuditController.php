<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers\Admin;

use App\Core\Controller;
use App\Modules\Documenti\Services\DocumentiAuditService;
use App\Traits\ControllerHelpers;

class AdminAuditController extends Controller
{
    use ControllerHelpers;

    private DocumentiAuditService $auditSvc;

    public function __construct()
    {
        $this->auditSvc = app(DocumentiAuditService::class);
    }

    public function index(): void
    {
        $user  = auth();
        $clean = $this->cleanGet(['q', 'entity', 'action', 'date_from', 'date_to', 'page']);
        $page  = max(1, (int) ($clean['page'] ?? 1));

        $data = $this->auditSvc->lista($clean, $page);

        $this->render('Documenti/Views/admin/audit', [
            'title'    => t('documenti.admin.audit_title'),
            'logs'     => $data['logs'],
            'total'    => $data['total'],
            'page'     => $page,
            'perPage'  => $data['perPage'],
            'pages'    => $data['pages'],
            'filters'  => $clean,
            'entities' => $this->auditSvc->entitaDistinte(),
            'actions'  => $this->auditSvc->azioniDistinte(),
            'user'     => $user,
        ]);
    }

    public function dettaglio(string $entity, string $id): void
    {
        $id   = (int) $id;
        $user = auth();
        $logs = $this->auditSvc->dettaglio($entity, $id);

        $this->render('Documenti/Views/admin/audit_dettaglio', [
            'title'    => t('documenti.admin.audit_dettaglio_title', ['entity' => $entity, 'id' => $id]),
            'logs'     => $logs,
            'entity'   => $entity,
            'entityId' => $id,
            'user'     => $user,
        ]);
    }

    public function exportCsv(): void
    {
        $clean = $this->cleanGet(['q', 'entity', 'action', 'date_from', 'date_to']);
        $rows  = $this->auditSvc->righeExport($clean);

        $fname = 'audit_documenti_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        // BOM per Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            t('documenti.admin.audit_csv.id'),
            t('documenti.admin.audit_csv.data'),
            t('documenti.admin.audit_csv.azione'),
            t('documenti.admin.audit_csv.entita'),
            t('documenti.admin.audit_csv.entity_id'),
            t('documenti.admin.audit_csv.utente'),
            t('documenti.admin.audit_csv.ip'),
        ], ';');
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'],
                $row['created_at'],
                $row['action'],
                $row['entity'],
                $row['entity_id'],
                $row['user_name'] ?? '—',
                $row['ip'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }
}
