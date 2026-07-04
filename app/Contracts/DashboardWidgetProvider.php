<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for modules that provide dashboard widgets.
 *
 * Register the FQCN in module.json:
 *   "dashboard_provider": "App\\Modules\\MyModule\\Providers\\MyDashboardProvider"
 *
 * For auto-discovery, place the provider in:
 *   app/Modules/{Module}/Providers/*DashboardProvider.php
 *
 * The contract is split in two so the dashboard can render a lightweight
 * skeleton first and then fill each widget body in parallel (one HTTP request
 * per widget), instead of computing everything sequentially up front:
 *
 *  - getWidgets()    → cheap metadata catalog (NO heavy queries / no HTTP).
 *  - getWidgetData() → computes one widget's payload on demand.
 *
 * Widget metadata (getWidgets) is an associative array:
 *   [
 *       'id'         => string,       // unique: 'modulename.widget_name'
 *       'type'       => string,       // 'stat'|'chart'|'list'|'html'
 *       'label'      => string,       // display name (catalog/settings default)
 *       'icon'       => string,       // FA icon class (e.g. 'fa-bell')
 *       'size'       => int,          // Bootstrap col: 3|4|6|12
 *       'permission' => string|null,  // required permission slug, null = all authenticated
 *       'cache_ttl'  => int,          // optional: seconds to cache the data (0 = no cache)
 *   ]
 *
 * Type-specific 'data' keys returned by getWidgetData:
 *   stat:  {value, subtitle, link, color}
 *   chart: {chartId, chartType, series, options}  (ApexCharts config)
 *   list:  {columns, rows, emptyMessage, link, iconColor}
 *   html:  {partial}                              (module view partial path)
 */
interface DashboardWidgetProvider
{
    /**
     * Lightweight widget catalog — metadata only, no expensive queries.
     *
     * Declare a widget here whenever it COULD be shown (cheap gating such as
     * module/permission checks is fine). Whether it actually renders is decided
     * later by getWidgetData(), which may return null to hide an empty widget.
     *
     * @param  int $userId  Authenticated user ID
     * @return array<int, array<string, mixed>>
     */
    public function getWidgets(int $userId): array;

    /**
     * Compute the payload for a single widget on demand.
     *
     * Return an array that is merged over the widget metadata — it MUST contain
     * a 'data' key with the type-specific payload, and MAY override 'label' /
     * 'icon' for widgets whose presentation is dynamic (e.g. weather).
     * Return null to skip the widget entirely (e.g. no data to show).
     *
     * @param  int    $userId    Authenticated user ID
     * @param  string $widgetId  The widget id from getWidgets()
     * @return array<string, mixed>|null
     */
    public function getWidgetData(int $userId, string $widgetId): ?array;
}
