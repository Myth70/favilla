/* Pagina Meteo — grafico orario (ApexCharts) + reload al cambio località. */
(function () {
    'use strict';

    function isDark() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark';
    }

    function buildChart() {
        var el = document.getElementById('mt-hourly-chart');
        if (!el || typeof ApexCharts === 'undefined' || el.dataset.rendered === '1') {
            return;
        }

        var data;
        try {
            data = JSON.parse(el.dataset.meteoHours || '[]');
        } catch (e) {
            return;
        }
        if (!data.length) {
            return;
        }

        var dark = isDark();
        var axis = dark ? '#9aa4b2' : '#64748b';
        var labels = data.map(function (d) { return d.t; });
        var temps = data.map(function (d) { return d.temp; });
        var apps = data.map(function (d) { return d.app; });
        var probs = data.map(function (d) { return d.prob; });

        // Bande "notte" (ore con is_day = false) come sfondo tenue del grafico.
        var nightFill = dark ? 'rgba(148,163,184,0.12)' : 'rgba(30,41,59,0.06)';
        var nightBands = [];
        var runStart = -1;
        for (var i = 0; i <= data.length; i++) {
            var isNight = i < data.length && data[i].day === false;
            if (isNight && runStart < 0) {
                runStart = i;
            } else if (!isNight && runStart >= 0) {
                var last = i - 1;
                var x2idx = (last + 1 < labels.length) ? last + 1 : last;
                nightBands.push({
                    x: labels[runStart],
                    x2: labels[x2idx],
                    fillColor: nightFill,
                    opacity: 1,
                    borderColor: 'transparent'
                });
                runStart = -1;
            }
        }

        var options = {
            annotations: { xaxis: nightBands },
            chart: {
                height: 260,
                type: 'line',
                fontFamily: 'inherit',
                toolbar: { show: false },
                animations: { enabled: true, easing: 'easeinout', speed: 600 }
            },
            series: [
                { name: 'Temperatura', type: 'area', data: temps },
                { name: 'Percepita', type: 'line', data: apps },
                { name: 'Prob. pioggia', type: 'column', data: probs }
            ],
            stroke: { width: [3, 2, 0], curve: 'smooth', dashArray: [0, 5, 0] },
            colors: ['#fb923c', '#d9772e', '#38bdf8'],
            fill: {
                type: ['gradient', 'solid', 'solid'],
                gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.04, stops: [0, 95] },
                opacity: [1, 1, 0.55]
            },
            plotOptions: { bar: { columnWidth: '38%', borderRadius: 3 } },
            dataLabels: { enabled: false },
            markers: { size: 0, hover: { size: 5 } },
            xaxis: {
                categories: labels,
                tickAmount: 8,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: axis, fontSize: '11px' } }
            },
            yaxis: [
                {
                    seriesName: 'Temperatura',
                    labels: { formatter: function (v) { return Math.round(v) + '°'; }, style: { colors: axis } }
                },
                {
                    // Percepita: asse nascosto agganciato alla scala di "Temperatura"
                    // (così le due linee restano allineate senza un secondo asse visibile).
                    seriesName: 'Temperatura',
                    show: false
                },
                {
                    seriesName: 'Prob. pioggia',
                    opposite: true, min: 0, max: 100,
                    labels: { formatter: function (v) { return Math.round(v) + '%'; }, style: { colors: axis } }
                }
            ],
            legend: { position: 'top', horizontalAlign: 'right', labels: { colors: dark ? '#cbd5e1' : '#475569' } },
            grid: { borderColor: dark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)', strokeDashArray: 4 },
            tooltip: {
                shared: true,
                intersect: false,
                custom: function (ctx) {
                    var d = data[ctx.dataPointIndex] || {};
                    var bg = dark ? '#1b2230' : '#ffffff';
                    var fg = dark ? '#e9eef6' : '#1e293b';
                    var mut = dark ? '#9aa4b2' : '#64748b';
                    var bd = dark ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.08)';
                    function row(c, lab, val) {
                        return '<div style="display:flex;align-items:center;gap:.75rem;justify-content:space-between;">'
                            + '<span style="display:inline-flex;align-items:center;gap:.4rem;color:' + mut + ';">'
                            + '<span style="width:8px;height:8px;border-radius:50%;background:' + c + ';"></span>' + lab + '</span>'
                            + '<strong style="color:' + fg + ';">' + val + '</strong></div>';
                    }
                    return '<div style="padding:.55rem .7rem;background:' + bg + ';border:1px solid ' + bd
                        + ';border-radius:.6rem;box-shadow:0 8px 24px rgba(0,0,0,.18);font-size:.78rem;min-width:172px;display:flex;flex-direction:column;gap:.3rem;">'
                        + '<div style="font-weight:700;color:' + fg + ';margin-bottom:.1rem;">' + (d.t || '') + '</div>'
                        + row('#fb923c', 'Temperatura', Math.round(d.temp) + '°')
                        + row('#d9772e', 'Percepita', Math.round(d.app) + '°')
                        + row('#38bdf8', 'Prob. pioggia', Math.round(d.prob) + '%')
                        + row(mut, 'Visibilità', (d.vis != null ? d.vis : '—') + ' km')
                        + '</div>';
                }
            }
        };

        var chart = new ApexCharts(el, options);
        chart.render();
        el.dataset.rendered = '1';
        el._apexChart = chart;
    }

    function rebuildChart() {
        var el = document.getElementById('mt-hourly-chart');
        if (el && el._apexChart) {
            el._apexChart.destroy();
            el._apexChart = null;
            el.dataset.rendered = '';
        }
        buildChart();
    }

    // ── Micro-animazioni (rispettano prefers-reduced-motion) ──────────────
    function motionOK() {
        return !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    // Reveal progressivo allo scroll. Lo stato nascosto in CSS è agganciato a
    // .mt-anim-ready, aggiunta qui solo quando le animazioni sono ammesse →
    // senza JS o con reduced-motion il contenuto resta interamente visibile.
    function initReveal() {
        var page = document.querySelector('.mt-page');
        if (!page || page.dataset.mtReveal === '1') { return; }
        if (!motionOK() || !('IntersectionObserver' in window)) { return; }
        var targets = page.querySelectorAll('.mt-current, .mt-section-head, .mt-bento, .mt-chart-card, .mt-trend, .mt-week, .mt-source');
        if (!targets.length) { return; }
        page.dataset.mtReveal = '1';
        page.classList.add('mt-anim-ready');
        Array.prototype.forEach.call(targets, function (el, i) {
            el.classList.add('mt-reveal');
            el.style.setProperty('--mt-reveal-i', i % 6);
        });
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('is-visible');
                    io.unobserve(e.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });
        Array.prototype.forEach.call(targets, function (el) { io.observe(el); });
    }

    // Count-up della temperatura grande dell'hero.
    function initTempCountUp() {
        var el = document.querySelector('.mt-current-temp');
        if (!el || el.dataset.counted === '1' || !motionOK()) { return; }
        var node = el.firstChild; // nodo di testo col numero (lo span del grado segue)
        if (!node || node.nodeType !== 3) { return; }
        var target = parseInt(node.nodeValue, 10);
        if (isNaN(target)) { return; }
        el.dataset.counted = '1';
        var dur = 750, t0 = performance.now();
        function tick(now) {
            var p = Math.min(1, (now - t0) / dur);
            var eased = 1 - Math.pow(1 - p, 3);
            node.nodeValue = String(Math.round(target * eased));
            if (p < 1) { requestAnimationFrame(tick); }
            else { node.nodeValue = String(target); }
        }
        requestAnimationFrame(tick);
    }

    function init() {
        buildChart();
        initReveal();
        initTempCountUp();
    }

    document.addEventListener('DOMContentLoaded', init);
    init();

    // Ricostruisci il grafico al cambio tema (evento globale del runtime Favilla).
    window.addEventListener('favilla:theme-state-changed', rebuildChart);

    // Dopo aver scelto una località (POST verso setLocation) ricarica la pagina.
    document.body.addEventListener('htmx:afterRequest', function (e) {
        try {
            var cfg = e.detail && e.detail.requestConfig;
            var target = e.target;
            if (e.detail && e.detail.successful && cfg && cfg.verb === 'post'
                && target && target.closest && target.closest('.mt-locsearch')) {
                window.location.reload();
            }
        } catch (x) { /* no-op */ }
    });
})();
