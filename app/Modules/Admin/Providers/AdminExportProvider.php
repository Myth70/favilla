<?php

declare(strict_types=1);

namespace App\Modules\Admin\Providers;

use App\Contracts\ExportableModule;
use App\Modules\Admin\Repositories\AdminLogsRepository;
use App\Modules\Admin\Repositories\AdminUserRepository;

class AdminExportProvider implements ExportableModule
{
    public function getDataSources(): array
    {
        return [
            [
                'key'        => 'users',
                'label'      => 'Utenti',
                'icon'       => 'fa-users',
                'permission' => 'admin.users.view',
                'fields'     => [
                    ['name' => 'id',         'label' => 'ID',            'type' => 'integer', 'sortable' => true,  'filterable' => false],
                    ['name' => 'name',       'label' => 'Nome',          'type' => 'string',  'sortable' => true,  'filterable' => true],
                    ['name' => 'email',      'label' => 'Email',         'type' => 'string',  'sortable' => true,  'filterable' => true],
                    ['name' => 'username',   'label' => 'Username',      'type' => 'string',  'sortable' => true,  'filterable' => true],
                    ['name' => 'is_active',  'label' => 'Attivo',        'type' => 'boolean', 'sortable' => true,  'filterable' => true],
                    ['name' => 'roles_list', 'label' => 'Ruoli',         'type' => 'string',  'sortable' => false, 'filterable' => false],
                    ['name' => 'created_at', 'label' => 'Data creazione','type' => 'datetime','sortable' => true,  'filterable' => true],
                ],
            ],
            [
                'key'        => 'audit_logs',
                'label'      => 'Log Audit',
                'icon'       => 'fa-clipboard-list',
                'permission' => 'admin.logs.view',
                'fields'     => [
                    ['name' => 'id',         'label' => 'ID',          'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'user_name',  'label' => 'Utente',      'type' => 'string',   'sortable' => false, 'filterable' => true],
                    ['name' => 'action',     'label' => 'Azione',      'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'entity',     'label' => 'Entit&agrave;','type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'entity_id',  'label' => 'ID Entit&agrave;','type' => 'integer','sortable' => false,'filterable' => false],
                    ['name' => 'ip',         'label' => 'IP',          'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'created_at', 'label' => 'Data',        'type' => 'datetime', 'sortable' => true,  'filterable' => true],
                ],
            ],
            [
                'key'        => 'login_attempts',
                'label'      => 'Tentativi Login',
                'icon'       => 'fa-right-to-bracket',
                'permission' => 'admin.logs.view',
                'fields'     => [
                    ['name' => 'id',         'label' => 'ID',          'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'email',      'label' => 'Email',       'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'ip',         'label' => 'IP',          'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'success',    'label' => 'Riuscito',    'type' => 'boolean',  'sortable' => true,  'filterable' => true],
                    ['name' => 'attempted_at','label' => 'Data tentativo','type' => 'datetime','sortable' => true, 'filterable' => true],
                ],
            ],
        ];
    }

    public function getExportData(
        string $sourceKey,
        array  $filters = [],
        string $sortBy = 'created_at',
        string $sortDir = 'DESC',
        int    $limit = 10000
    ): array {
        switch ($sourceKey) {
            case 'users':
                return $this->exportUsers($filters, $sortBy, $sortDir, $limit);
            case 'audit_logs':
                return $this->exportAuditLogs($filters, $sortBy, $sortDir, $limit);
            case 'login_attempts':
                return $this->exportLoginAttempts($filters, $sortBy, $sortDir, $limit);
            default:
                return [];
        }
    }

    public function getExportModuleName(): string
    {
        return 'Admin';
    }

    public function getExportModuleIcon(): string
    {
        return 'fa-shield-halved';
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function exportUsers(array $filters, string $sortBy, string $sortDir, int $limit): array
    {
        $repo = app(AdminUserRepository::class);
        $result = $repo->listWithRoles($filters, 1, $limit);
        return $result['items'] ?? [];
    }

    private function exportAuditLogs(array $filters, string $sortBy, string $sortDir, int $limit): array
    {
        $allowedSorts = ['created_at', 'action', 'entity', 'ip'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $dir  = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $filters['sort'] = $sort;
        $filters['dir']  = $dir;

        $repo = app(AdminLogsRepository::class);
        $result = $repo->listAudit($filters, 1, $limit);
        return $result['items'] ?? [];
    }

    private function exportLoginAttempts(array $filters, string $sortBy, string $sortDir, int $limit): array
    {
        $allowedSorts = ['created_at', 'email', 'ip_address', 'success'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $dir  = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $filters['sort'] = $sort;
        $filters['dir']  = $dir;

        $repo = app(AdminLogsRepository::class);
        $result = $repo->listAttempts($filters, 1, $limit);

        // Remap ip_address → ip and created_at → attempted_at for consistent field names
        return array_map(function (array $row) {
            $row['ip']           = $row['ip_address'] ?? '';
            $row['attempted_at'] = $row['created_at'] ?? '';
            return $row;
        }, $result['items'] ?? []);
    }

    public function getSingleRecord(string $sourceKey, int $recordId): ?array
    {
        if ($sourceKey === 'users') {
            $repo = app(AdminUserRepository::class);
            $user = $repo->find($recordId);
            if ($user === null) {
                return null;
            }

            // Enrich with roles list
            $pdo = app(\PDO::class);
            $stmt = $pdo->prepare(
                "SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ')
                 FROM user_role ur
                 JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = ?"
            );
            $stmt->execute([$recordId]);
            $user['roles_list'] = $stmt->fetchColumn() ?: '';

            return $user;
        }

        if ($sourceKey === 'audit_logs') {
            $pdo = app(\PDO::class);
            $stmt = $pdo->prepare(
                'SELECT a.*, u.name AS user_name
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.id = ?
                 LIMIT 1'
            );
            $stmt->execute([$recordId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        if ($sourceKey === 'login_attempts') {
            $pdo = app(\PDO::class);
            $stmt = $pdo->prepare(
                'SELECT id, email, ip_address AS ip, success, created_at AS attempted_at
                 FROM login_attempts
                 WHERE id = ?
                 LIMIT 1'
            );
            $stmt->execute([$recordId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        return null;
    }
}
