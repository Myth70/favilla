<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Documenti\Repositories\DocumentoRepository;
use App\Modules\Documenti\Services\DocumentoService;

class DocumentiDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        if (!has_permission('documenti.access')) {
            return [];
        }

        $widgets = [
            [
                'id'         => 'documenti.in_scadenza',
                'type'       => 'stat',
                'label'      => t('documenti.widget.in_scadenza_label'),
                'icon'       => 'fa-file-circle-exclamation',
                'size'       => 3,
                'permission' => 'documenti.view',
            ],
            [
                'id'         => 'documenti.scadenze_list',
                'type'       => 'list',
                'label'      => t('documenti.widget.scadenze_list_label'),
                'icon'       => 'fa-hourglass-half',
                'size'       => 6,
                'permission' => 'documenti.view',
            ],
        ];

        // Widget di workflow: solo per chi ha permessi di controllo/approvazione/admin.
        if (has_permission('documenti.controllo') || has_permission('documenti.approvazione') || has_permission('documenti.admin')) {
            $widgets[] = [
                'id'         => 'documenti.da_approvare',
                'type'       => 'stat',
                'label'      => t('documenti.widget.da_approvare_label'),
                'icon'       => 'fa-file-signature',
                'size'       => 3,
                'permission' => 'documenti.inbox',
            ];
            $widgets[] = [
                'id'         => 'documenti.inbox_list',
                'type'       => 'list',
                'label'      => t('documenti.widget.inbox_list_label'),
                'icon'       => 'fa-inbox',
                'size'       => 6,
                'permission' => 'documenti.inbox',
            ];
        }

        return $widgets;
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        return match ($widgetId) {
            'documenti.in_scadenza'    => $this->inScadenzaWidget($userId),
            'documenti.scadenze_list'  => $this->scadenzeListWidget($userId),
            'documenti.da_approvare'   => $this->daApprovareWidget(),
            'documenti.inbox_list'     => $this->inboxListWidget(),
            default                    => null,
        };
    }

    private function scadenzeQuery(int $userId): array
    {
        try {
            $docRepo = app(DocumentoRepository::class);
            return $docRepo->listPaginated([
                'scadenza'        => 'prossimi_30',
                'stato'           => ['pubblicato'],
                'sort'            => 'scade_il',
                'dir'             => 'ASC',
                'current_user_id' => $userId,
            ], false);
        } catch (\Throwable) {
            return ['total' => 0, 'data' => []];
        }
    }

    private function inScadenzaWidget(int $userId): array
    {
        $result     = $this->scadenzeQuery($userId);
        $inScadenza = (int) ($result['total'] ?? 0);

        return [
            'data' => [
                'value'    => $inScadenza,
                'subtitle' => t('documenti.widget.in_scadenza_subtitle'),
                'link'     => route('documenti.scadenze'),
                'color'    => $inScadenza > 0 ? 'warning' : 'success',
            ],
        ];
    }

    private function scadenzeListWidget(int $userId): array
    {
        $result = $this->scadenzeQuery($userId);
        $rows   = $this->buildScadenzaRows($result['data'] ?? []);

        return [
            'data' => [
                'columns'      => [t('documenti.widget.col_documento'), t('documenti.widget.col_categoria'), t('documenti.widget.col_scadenza')],
                'rows'         => $rows,
                'emptyMessage' => t('documenti.widget.scadenze_list_empty'),
                'link'         => route('documenti.scadenze'),
                'iconColor'    => 'warning',
            ],
        ];
    }

    private function daApprovareWidget(): array
    {
        $daApprovare = 0;
        try {
            $kpi = app(DocumentoRepository::class)->kpiByStato();
            foreach ($kpi as $row) {
                if (in_array($row['stato'], ['inviato', 'in_controllo', 'controllato', 'in_approvazione'], true)) {
                    $daApprovare += (int) $row['totale'];
                }
            }
        } catch (\Throwable) {
            $daApprovare = 0;
        }

        return [
            'data' => [
                'value'    => $daApprovare,
                'subtitle' => t('documenti.widget.da_approvare_subtitle'),
                'link'     => route('documenti.inbox'),
                'color'    => $daApprovare > 0 ? 'warning' : 'success',
            ],
        ];
    }

    private function inboxListWidget(): array
    {
        $inboxRows = [];
        try {
            $inbox     = app(DocumentoService::class)->inboxFor();
            $inboxRows = $this->buildInboxRows($inbox['items'] ?? ($inbox['data'] ?? []));
        } catch (\Throwable) {
            $inboxRows = [];
        }

        return [
            'data' => [
                'columns'      => [t('documenti.widget.col_documento'), t('documenti.widget.col_categoria'), t('documenti.widget.col_stato')],
                'rows'         => $inboxRows,
                'emptyMessage' => t('documenti.widget.inbox_list_empty'),
                'link'         => route('documenti.inbox'),
                'iconColor'    => 'warning',
            ],
        ];
    }

    /**
     * Righe per la lista "documenti in scadenza".
     *
     * @param  array<int, array<string, mixed>> $docs
     * @return array<int, array<int, mixed>>
     */
    private function buildScadenzaRows(array $docs): array
    {
        $rows  = [];
        $today = strtotime('today');

        foreach (array_slice($docs, 0, 5) as $doc) {
            $id     = (int) ($doc['id'] ?? 0);
            $titolo = trim((string) ($doc['titolo'] ?? '')) ?: t('documenti.widget.documento_fallback');
            $cat    = trim((string) ($doc['categoria_nome'] ?? ''));
            $scade  = (string) ($doc['scade_il'] ?? '');

            $titleHtml = '<a href="' . e(route('documenti.show', ['id' => $id])) . '" class="text-decoration-none">' . e($titolo) . '</a>';

            $scadeHtml = '<span class="text-muted">—</span>';
            $ts = $scade !== '' ? strtotime($scade) : false;
            if ($ts !== false) {
                $days  = (int) floor(($ts - $today) / 86400);
                $color = $days <= 7 ? 'danger' : ($days <= 14 ? 'warning' : 'secondary');
                $label = format_date(date('Y-m-d H:i:s', $ts), 'short');
                $scadeHtml = '<span class="badge bg-' . $color . ' bg-opacity-10 text-' . $color . '">' . e($label) . '</span>';
            }

            $rows[] = [
                ['html' => $titleHtml],
                $cat !== '' ? $cat : '—',
                ['html' => $scadeHtml],
            ];
        }

        return $rows;
    }

    /**
     * Righe per la lista "inbox approvazioni".
     *
     * @param  array<int, array<string, mixed>> $items
     * @return array<int, array<int, mixed>>
     */
    private function buildInboxRows(array $items): array
    {
        $rows = [];
        foreach (array_slice($items, 0, 5) as $doc) {
            $id     = (int) ($doc['id'] ?? 0);
            $titolo = trim((string) ($doc['titolo'] ?? '')) ?: t('documenti.widget.documento_fallback');
            $cat    = trim((string) ($doc['categoria_nome'] ?? ''));
            $stato  = (string) ($doc['stato'] ?? '');

            $titleHtml = '<a href="' . e(route('documenti.show', ['id' => $id])) . '" class="text-decoration-none">' . e($titolo) . '</a>';

            $statoColor = match ($stato) {
                'inviato', 'controllato' => 'info',
                'in_approvazione'        => 'warning',
                'in_controllo'           => 'primary',
                default                  => 'secondary',
            };
            $statoLabel = in_array($stato, ['inviato', 'in_controllo', 'controllato', 'in_approvazione'], true)
                ? t('documenti.stato.' . $stato)
                : ucfirst(str_replace('_', ' ', $stato));
            $statoHtml = '<span class="badge bg-' . $statoColor . ' bg-opacity-10 text-' . $statoColor . '">' . e($statoLabel) . '</span>';

            $rows[] = [
                ['html' => $titleHtml],
                $cat !== '' ? $cat : '—',
                ['html' => $statoHtml],
            ];
        }

        return $rows;
    }
}
