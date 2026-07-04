/**
 * Progetti — Report di chiusura
 * Inizializza i grafici ApexCharts a partire dai dati serializzati
 * dal server negli script id="prj-report-*" (vedi Views/report.php).
 */
(function () {
    'use strict';

    function readJson(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        try { return JSON.parse(el.textContent || el.innerHTML); } catch (_) { return null; }
    }

    function apexTheme() {
        var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        return {
            mode: isDark ? 'dark' : 'light',
            foreColor: isDark ? '#adb5bd' : '#6c757d',
            background: 'transparent',
        };
    }

    function initCharts() {
        if (typeof ApexCharts === 'undefined') return;

        var theme = apexTheme();

        /* ── Donut: Attivita per stato ── */
        var donutEl = document.getElementById('prj-chart-donut');
        var statusRows = readJson('prj-report-task-status') || [];
        var statusLabelsMap = readJson('prj-report-status-labels') || {};
        if (donutEl && statusRows.length > 0) {
            var labels = statusRows.map(function (r) { return statusLabelsMap[r.status] || r.status; });
            var series = statusRows.map(function (r) { return parseInt(r.cnt, 10) || 0; });

            if (series.some(function (v) { return v > 0; })) {
                new ApexCharts(donutEl, {
                    chart: { type: 'donut', height: 260, background: theme.background, toolbar: { show: false }, animations: { speed: 400 } },
                    theme: { mode: theme.mode },
                    series: series,
                    labels: labels,
                    legend: { position: 'bottom', fontSize: '12px', offsetY: 4 },
                    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 600 }, dropShadow: { enabled: false } },
                    plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: t('js.progetti.total_label', 'Totale'), fontSize: '13px', fontWeight: 700, color: theme.foreColor } } } } },
                    tooltip: { y: { formatter: function (v) { return v + ' ' + t('js.progetti.tasks_word', 'attivita'); } } },
                    stroke: { width: 0 },
                }).render();
            }
        }

        /* ── Bar orizzontale: Ore per membro ── */
        var barEl = document.getElementById('prj-chart-hours-user');
        var userRows = readJson('prj-report-hours-user') || [];
        if (barEl && userRows.length > 0) {
            var names = userRows.map(function (r) { return r.user_name; });
            var hours = userRows.map(function (r) { return parseFloat(r.hours) || 0; });
            var barHeight = Math.max(220, names.length * 42);

            new ApexCharts(barEl, {
                chart: { type: 'bar', height: barHeight, background: theme.background, toolbar: { show: false }, animations: { speed: 400 } },
                theme: { mode: theme.mode },
                series: [{ name: t('js.progetti.hours_word', 'Ore'), data: hours }],
                xaxis: { categories: names, labels: { style: { fontSize: '12px' } } },
                yaxis: { labels: { formatter: function (v) { return v + ' h'; }, style: { fontSize: '11px' } } },
                plotOptions: { bar: { horizontal: true, borderRadius: 4, dataLabels: { position: 'top' } } },
                dataLabels: { enabled: true, formatter: function (v) { return v + ' h'; }, offsetX: 6, style: { fontSize: '11px', fontWeight: 600, colors: [theme.foreColor] } },
                colors: ['#0dcaf0'],
                grid: { xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } }, borderColor: 'rgba(128,128,128,0.15)' },
                tooltip: { x: { show: true } },
            }).render();
        }

        /* ── Area: Trend ore settimanale ── */
        var areaEl = document.getElementById('prj-chart-trend');
        var trendRows = readJson('prj-report-hours-trend') || [];
        if (areaEl && trendRows.length > 1) {
            var dates = trendRows.map(function (r) { return r.week_start; });
            var trendHours = trendRows.map(function (r) { return parseFloat(r.hours) || 0; });

            new ApexCharts(areaEl, {
                chart: { type: 'area', height: 220, background: theme.background, toolbar: { show: false }, animations: { speed: 400 }, sparkline: { enabled: false } },
                theme: { mode: theme.mode },
                series: [{ name: t('js.progetti.hours_word', 'Ore'), data: trendHours }],
                xaxis: { categories: dates, labels: { style: { fontSize: '11px' }, rotate: -30 }, tickAmount: Math.min(dates.length, 10) },
                yaxis: { labels: { formatter: function (v) { return v + ' h'; }, style: { fontSize: '11px' } }, min: 0 },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
                colors: ['#198754'],
                dataLabels: { enabled: false },
                grid: { borderColor: 'rgba(128,128,128,0.15)' },
                markers: { size: 4, strokeWidth: 0, hover: { size: 6 } },
                tooltip: { y: { formatter: function (v) { return v + ' h'; } } },
            }).render();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }
})();
