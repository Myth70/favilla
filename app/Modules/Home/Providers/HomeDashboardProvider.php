<?php

declare(strict_types=1);

namespace App\Modules\Home\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Calendar\Services\CalendarService;
use App\Modules\Contacts\Services\ContactsReminderService;
use App\Modules\Home\Services\DashboardColorPalette;

class HomeDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        $widgets = [
            [
                'id'         => 'home.quick_links',
                'type'       => 'html',
                'label'      => t('home.widget.quick_access'),
                'icon'       => 'fa-th-large',
                'size'       => 12,
                'permission' => null,
            ],
        ];

        // Cheap gating: the timeline only makes sense with Calendar or Contacts.
        if ($this->timelineEnabled()) {
            $widgets[] = [
                'id'         => 'home.weekly_timeline',
                'type'       => 'list',
                'label'      => t('home.widget.timeline_label'),
                'icon'       => 'fa-calendar-week',
                'size'       => 6,
                'permission' => null,
            ];
        }

        return $widgets;
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        return match ($widgetId) {
            'home.quick_links'     => $this->quickLinksData(),
            'home.weekly_timeline' => $this->weeklyTimelineData($userId),
            default                => null,
        };
    }

    private function quickLinksData(): array
    {
        $colorPalette = app(DashboardColorPalette::class);

        // Quick links from NavigationRegistry surface "quick_access".
        // renderPartial non inietta shared data, quindi usiamo direttamente l'helper.
        $quickLinks = [];
        foreach ($colorPalette->assignQuickAccessColors(navigation('quick_access')) as $item) {
            $route = $item['route'] ?? '';
            if ($route === '') {
                continue;
            }
            $quickLinks[] = [
                'label' => $item['label'],
                'icon'  => $item['icon'],
                'route' => $route,
                'color' => $item['color'] ?? 'primary',
            ];
        }

        return ['data' => [
            'partial'    => 'Home/Views/partials/widgets/quick_links',
            'quickLinks' => $quickLinks,
        ]];
    }

    private function timelineEnabled(): bool
    {
        $canCalendario = isModuleEnabled('Calendar') && has_permission('calendar.view');
        $canContatti = isModuleEnabled('Contacts') && has_permission('contacts.view');

        return $canCalendario || $canContatti;
    }

    private function weeklyTimelineData(int $userId): ?array
    {
        $colorPalette = app(DashboardColorPalette::class);
        $canCalendario = isModuleEnabled('Calendar') && has_permission('calendar.view');
        $canContatti = isModuleEnabled('Contacts') && has_permission('contacts.view');

        if (!$canCalendario && !$canContatti) {
            return null;
        }

        $items = [];

        if ($canCalendario) {
            try {
                $calendario = app(CalendarService::class);
                $events = $calendario->getUpcomingEvents($userId, 8);
                $calCutoff = strtotime('+7 days');

                foreach ($events as $event) {
                    $whenRaw = (string) ($event['start_datetime'] ?? '');
                    $ts = $whenRaw !== '' ? strtotime($whenRaw) : false;
                    if ($ts === false) {
                        continue;
                    }
                    if ($ts > $calCutoff) {
                        continue;
                    }

                    $eventId = (int) ($event['id'] ?? 0);
                    $title = trim((string) ($event['title'] ?? ''));
                    if ($title === '') {
                        $title = t('home.widget.event_fallback');
                    }

                    $items[] = [
                        'ts'     => $ts,
                        'type'   => t('home.widget.type_calendar'),
                        'kind'   => 'calendar',
                        'color'  => $colorPalette->lookupPaletteColor('calendar', 'calendar') ?? 'info',
                        'title'  => $title,
                        'link'   => route('calendar.show', ['id' => $eventId]),
                        'source' => route('calendar.index'),
                    ];
                }
            } catch (\Throwable) {
                // Keep dashboard resilient if module data is temporarily unavailable.
            }
        }

        if ($canContatti) {
            try {
                $contatti = app(ContactsReminderService::class);
                $ricorrenze = $contatti->getProssime($userId, 7);

                foreach ($ricorrenze as $ric) {
                    $dateRaw = (string) ($ric['prossima_data'] ?? '');
                    $ts = $dateRaw !== '' ? strtotime($dateRaw . ' 00:00:00') : false;
                    if ($ts === false) {
                        continue;
                    }

                    $contattoId = (int) ($ric['contatto_id'] ?? 0);
                    $nomeContatto = trim((string) (($ric['nome'] ?? '') . ' ' . ($ric['cognome'] ?? '')));
                    $titolo = trim((string) ($ric['titolo'] ?? ''));
                    if ($titolo === '') {
                        $titolo = t('home.widget.recurrence_fallback');
                    }
                    $fullTitle = trim($titolo . ' - ' . $nomeContatto, ' -');
                    if ($fullTitle === '') {
                        $fullTitle = t('home.widget.recurrence_fallback');
                    }

                    $items[] = [
                        'ts'     => $ts,
                        'type'   => t('home.widget.type_contacts'),
                        'kind'   => 'contacts',
                        'color'  => $colorPalette->lookupPaletteColor('contacts', 'contacts') ?? 'primary',
                        'title'  => $fullTitle,
                        'link'   => route('contacts.show', ['id' => $contattoId]),
                        'source' => route('contacts.index'),
                    ];
                }
            } catch (\Throwable) {
                // Keep dashboard resilient if module data is temporarily unavailable.
            }
        }

        usort($items, static fn (array $a, array $b): int => $a['ts'] <=> $b['ts']);
        $items = array_slice($items, 0, 8);

        $todayTs = strtotime(date('Y-m-d') . ' 00:00:00');
        $rows = [];

        foreach ($items as $item) {
            $days = (int) floor(($item['ts'] - $todayTs) / 86400);
            if ($days < 0) {
                continue;
            }
            if ($days === 0) {
                $statusHtml = '<span class="badge bg-danger bg-opacity-10 text-danger">' . e(t('home.widget.today')) . '</span>';
            } elseif ($days === 1) {
                $statusHtml = '<span class="badge bg-warning bg-opacity-10 text-warning">' . e(t('home.widget.tomorrow')) . '</span>';
            } else {
                $statusHtml = '<span class="badge bg-secondary bg-opacity-10 text-secondary">' . e(t('home.widget.in_days', ['count' => $days])) . '</span>';
            }

            $typeHtml = '<span class="badge bg-' . e($item['color']) . ' bg-opacity-10 text-' . e($item['color']) . '">' . e($item['type']) . '</span>';
            $titleHtml = '<a href="' . e($item['link']) . '" class="text-decoration-none">' . e($item['title']) . '</a>';
            $tsInt = (int) $item['ts'];
            if ($item['kind'] === 'calendar') {
                $when = format_date_it(date('Y-m-d H:i:s', $tsInt), 'short');
                if (date('H:i', $tsInt) !== '00:00') {
                    $when .= ' ' . date('H:i', $tsInt);
                }
            } else {
                $when = format_date_it(date('Y-m-d H:i:s', $tsInt), 'compact');
            }

            $rows[] = [
                ['html' => $typeHtml],
                ['html' => $titleHtml],
                $when,
                ['html' => $statusHtml],
            ];
        }

        $fallbackLink = $canCalendario ? route('calendar.index') : route('contacts.index');

        return ['data' => [
            'columns'      => [t('home.widget.col_type'), t('home.widget.col_item'), t('home.widget.col_when'), t('home.widget.col_status')],
            'rows'         => $rows,
            'emptyMessage' => t('home.widget.timeline_empty'),
            'link'         => $fallbackLink,
            'iconColor'    => 'info',
        ]];
    }
}
