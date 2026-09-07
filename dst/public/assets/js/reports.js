/**
 * Report charts. Chart.js is pulled from the CDN only when a page actually has
 * a chart on it, the same way command-center.js does it.
 */
(function () {
    'use strict';

    var specs = window.rpCharts || {};
    if (!Object.keys(specs).length) return;

    var PALETTE = ['#8a72f0', '#2dd4bf', '#fb923c', '#f43f6d', '#38bdf8', '#facc15', '#22c55e', '#a78bfa', '#f87171', '#34d399'];

    function isDark() {
        return document.body.classList.contains('theme-dark');
    }

    function loadChartJs() {
        if (window.Chart) return Promise.resolve();
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    function colorsFor(spec, dataset, count) {
        if (dataset.colors) return dataset.colors;
        if (spec.kind === 'doughnut') return PALETTE.slice(0, count).concat(PALETTE).slice(0, count);
        return dataset.color || PALETTE[0];
    }

    function build(canvas, spec) {
        var dark = isDark();
        var grid = dark ? 'rgba(148,163,184,.16)' : 'rgba(15,23,42,.08)';
        var text = dark ? '#94a3b8' : '#64748b';

        var datasets = spec.datasets.map(function (d) {
            var colors = colorsFor(spec, d, spec.labels.length);
            return {
                label: d.label,
                data: d.data,
                backgroundColor: spec.kind === 'line' ? 'rgba(138,114,240,.15)' : colors,
                borderColor: spec.kind === 'line' ? (d.color || PALETTE[0]) : colors,
                borderWidth: spec.kind === 'line' ? 2 : 0,
                borderRadius: spec.kind === 'bar' ? 6 : 0,
                fill: spec.kind === 'line',
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: d.color || PALETTE[0]
            };
        });

        var isPie = spec.kind === 'doughnut';

        new window.Chart(canvas.getContext('2d'), {
            type: isPie ? 'doughnut' : spec.kind,
            data: { labels: spec.labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: spec.horizontal ? 'y' : 'x',
                cutout: isPie ? '58%' : undefined,
                plugins: {
                    legend: {
                        display: isPie || datasets.length > 1,
                        position: isPie ? 'right' : 'top',
                        labels: { color: text, boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var v = ctx.parsed.y !== undefined && !spec.horizontal ? ctx.parsed.y
                                    : (ctx.parsed.x !== undefined && spec.horizontal ? ctx.parsed.x : ctx.parsed);
                                return ' ' + (ctx.dataset.label || ctx.label) + ': ' + Number(v).toLocaleString();
                            }
                        }
                    }
                },
                scales: isPie ? {} : {
                    x: {
                        grid: { color: spec.horizontal ? grid : 'transparent', drawBorder: false },
                        ticks: { color: text, font: { size: 11 } }
                    },
                    y: {
                        grid: { color: spec.horizontal ? 'transparent' : grid, drawBorder: false },
                        ticks: {
                            color: text,
                            font: { size: 11 },
                            callback: function (value) {
                                if (spec.horizontal) return this.getLabelForValue(value);
                                return Math.abs(value) >= 1000 ? (value / 1000).toLocaleString() + 'k' : value;
                            }
                        }
                    }
                }
            }
        });
    }

    loadChartJs().then(function () {
        Object.keys(specs).forEach(function (id) {
            var canvas = document.getElementById(id);
            if (canvas) build(canvas, specs[id]);
        });
    }).catch(function (e) {
        console.warn('Chart.js could not be loaded.', e);
    });
}());
