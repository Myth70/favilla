<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Contacts\Services\ContactsReminderService;
use App\Modules\Contacts\Services\ContactsService;

class ContactsDashboardProvider implements DashboardWidgetProvider
{
    private const TIPO_MAP = [
        'compleanno'   => ['color' => 'info',      'icon' => 'fa-cake-candles'],
        'anniversario' => ['color' => 'danger',    'icon' => 'fa-heart'],
        'evento'       => ['color' => 'secondary', 'icon' => 'fa-calendar-day'],
    ];

    /** Locale-aware label for a recurrence type (const can't call t()). */
    private function tipoLabel(string $tipo): string
    {
        return match ($tipo) {
            'compleanno'   => t('contacts.widget.type_birthday'),
            'anniversario' => t('contacts.widget.type_anniversary'),
            'evento'       => t('contacts.widget.type_event'),
            default        => t('contacts.widget.recurrence_fallback'),
        };
    }

    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'contacts.ricorrenze',
                'type'       => 'stat',
                'label'      => t('contacts.widget.recurrences_label'),
                'icon'       => 'fa-calendar-day',
                'size'       => 3,
                'permission' => 'contacts.view',
            ],
            [
                'id'         => 'contacts.recurrences_list',
                'type'       => 'list',
                'label'      => t('contacts.widget.recurrences_list_label'),
                'icon'       => 'fa-gift',
                'size'       => 6,
                'permission' => 'contacts.view',
            ],
            [
                'id'         => 'contacts.totali',
                'type'       => 'stat',
                'label'      => t('contacts.widget.totals_label'),
                'icon'       => 'fa-address-book',
                'size'       => 3,
                'permission' => 'contacts.view',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        return match ($widgetId) {
            'contacts.ricorrenze'       => $this->ricorrenzeData($userId),
            'contacts.recurrences_list' => $this->recurrencesListData($userId),
            'contacts.totali'           => $this->totaliData($userId),
            default                     => null,
        };
    }

    private function ricorrenzeData(int $userId): array
    {
        $prossime = $this->getProssime($userId);

        $count   = count($prossime);
        $urgenti = 0;
        foreach ($prossime as $ric) {
            $g = $ric['giorni_mancanti'] ?? null;
            if (is_int($g) && $g <= 7) {
                $urgenti++;
            }
        }

        return ['data' => [
            'value'    => $count,
            'subtitle' => $urgenti > 0 ? t('contacts.widget.within7_sub', ['count' => $urgenti]) : t('contacts.widget.within30_sub'),
            'link'     => route('contacts.index'),
            'color'    => $urgenti > 0 ? 'warning' : ($count > 0 ? 'primary' : 'success'),
        ]];
    }

    private function recurrencesListData(int $userId): array
    {
        return ['data' => [
            'columns'      => [t('contacts.widget.col_recurrence'), t('contacts.widget.col_contact'), t('contacts.widget.col_when')],
            'rows'         => $this->buildRows($this->getProssime($userId)),
            'emptyMessage' => t('contacts.widget.empty'),
            'link'         => route('contacts.index'),
            'iconColor'    => 'primary',
        ]];
    }

    private function totaliData(int $userId): ?array
    {
        try {
            $stats = app(ContactsService::class)->getStats($userId);
        } catch (\Throwable) {
            return null;
        }

        return ['data' => [
            'value'    => (int) ($stats['totale'] ?? 0),
            'subtitle' => t('contacts.widget.favorites_sub', ['count' => (int) ($stats['preferiti'] ?? 0)]),
            'link'     => route('contacts.index'),
            'color'    => 'teal',
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getProssime(int $userId): array
    {
        try {
            return app(ContactsReminderService::class)->getProssime($userId, 30);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<int, array<string, mixed>> $prossime
     * @return array<int, array<int, mixed>>
     */
    private function buildRows(array $prossime): array
    {
        $rows = [];
        foreach (array_slice($prossime, 0, 6) as $ric) {
            $cid    = (int) ($ric['contatto_id'] ?? 0);
            $titolo = trim((string) ($ric['titolo'] ?? ''));
            $tipo   = (string) ($ric['tipo'] ?? '');
            $nome   = trim((string) (($ric['nome'] ?? '') . ' ' . ($ric['cognome'] ?? '')));
            if ($nome === '') {
                $nome = t('contacts.widget.contact_fallback');
            }
            $giorni = $ric['giorni_mancanti'] ?? null;
            $data   = (string) ($ric['prossima_data'] ?? '');

            $cfg   = self::TIPO_MAP[$tipo] ?? ['color' => 'secondary', 'icon' => 'fa-calendar-day'];
            $label = $titolo !== '' ? $titolo : $this->tipoLabel($tipo);

            $ricHtml = '<span class="badge bg-' . $cfg['color'] . ' bg-opacity-10 text-' . $cfg['color'] . '">'
                . '<i class="fa-solid ' . $cfg['icon'] . ' me-1"></i>' . e($label) . '</span>';
            $contattoHtml = '<a href="' . e(route('contacts.show', ['id' => $cid])) . '" class="text-decoration-none">' . e($nome) . '</a>';

            $rows[] = [
                ['html' => $ricHtml],
                ['html' => $contattoHtml],
                ['html' => $this->whenBadge($data, is_int($giorni) ? $giorni : null)],
            ];
        }
        return $rows;
    }

    private function whenBadge(string $date, ?int $giorni): string
    {
        $label = $date !== '' ? format_date_it($date . ' 00:00:00', 'short') : '';

        if ($giorni === null) {
            return $label !== '' ? '<span class="text-muted">' . e($label) . '</span>' : '<span class="text-muted">—</span>';
        }

        if ($giorni === 0) {
            $color = 'danger';
            $extra = t('contacts.widget.today');
        } elseif ($giorni === 1) {
            $color = 'warning';
            $extra = t('contacts.widget.tomorrow');
        } elseif ($giorni <= 7) {
            $color = 'warning';
            $extra = t('contacts.widget.in_days', ['count' => $giorni]);
        } else {
            $color = 'secondary';
            $extra = t('contacts.widget.in_days', ['count' => $giorni]);
        }

        $datePart = $label !== '' ? '<span class="me-1">' . e($label) . '</span>' : '';
        return $datePart . '<span class="badge bg-' . $color . ' bg-opacity-10 text-' . $color . '">' . e($extra) . '</span>';
    }
}
