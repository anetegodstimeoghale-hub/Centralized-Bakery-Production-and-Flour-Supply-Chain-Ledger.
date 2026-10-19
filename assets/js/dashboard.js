/* ==========================================================================
 * assets/js/dashboard.js
 * --------------------------------------------------------------------------
 * Reads the JSON written by dashboard/index.php and draws two charts:
 *
 *   1. productionChart : LINE chart - demand vs planned vs actual units
 *   2. flourChart      : BAR  chart - kilograms of flour per day
 *
 * The data block in the page looks like:
 *   <script type="application/json" id="bakery-chart-data">{ ... }</script>
 * ========================================================================== */
(function () {
    'use strict';

    var dataNode = document.getElementById('bakery-chart-data');
    if (!dataNode) {
        return;
    }

    var data;
    try {
        data = JSON.parse(dataNode.textContent);
    } catch (err) {
        console.error('Chart data is not valid JSON', err);
        return;
    }

    // Chart.js could not be loaded (missing vendor file)
    if (typeof Chart === 'undefined') {
        var box = document.querySelector('.chart-canvas-wrap');
        if (box) {
            box.innerHTML = '<div class="alert alert-warning mb-0">' +
                'Chart.js could not be loaded. Check that ' +
                '<code>assets/vendor/chart.umd.min.js</code> exists.</div>';
        }
        return;
    }

    Chart.defaults.font.family = "system-ui, -apple-system, 'Segoe UI', sans-serif";

    /* -----------------------------------------------------------------
     * THEME PALETTE
     * The chart colours follow the light / dark mode selected with the
     * ☀️/🌙 switch in the top navigation (assets/js/theme.js).
     * ----------------------------------------------------------------- */
    function themePalette() {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

        if (dark) {                          /* dark chocolate background */
            return {
                axisText: '#b3a494',
                grid: 'rgba(255, 255, 255, .09)',
                demand: '#b0a08f',
                demandFill: 'rgba(176, 160, 143, .14)',
                planned: '#f08a55',
                plannedFill: 'rgba(240, 138, 85, .16)',
                actual: '#7fd7a8',
                actualFill: 'rgba(127, 215, 168, .16)',
                bar: 'rgba(214, 150, 90, .85)',
                barBorder: '#d69a5a'
            };
        }

        return {                             /* cream bakery background */
            axisText: '#7a6a5d',
            grid: 'rgba(45, 28, 12, .07)',
            demand: '#8c7b6b',
            demandFill: 'rgba(140, 123, 107, .10)',
            planned: '#b94f22',
            plannedFill: 'rgba(185, 79, 34, .12)',
            actual: '#2f7d4f',
            actualFill: 'rgba(47, 125, 79, .12)',
            bar: 'rgba(109, 70, 32, .78)',
            barBorder: '#6d4620'
        };
    }

    var palette = themePalette();
    var prodChart = null;
    var flourChart = null;

    Chart.defaults.color = palette.axisText;   /* warm muted grey (matches style.css) */

    /* -----------------------------------------------------------------
     * 1. PRODUCTION vs DEMAND (line chart)
     * ----------------------------------------------------------------- */
    var prodCanvas = document.getElementById('productionChart');
    if (prodCanvas) {
        prodChart = new Chart(prodCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Total demand (units)',
                        data: data.demand,
                        borderColor: palette.demand,
                        backgroundColor: palette.demandFill,
                        borderDash: [6, 4],
                        tension: 0.25,
                        pointRadius: 3,
                        fill: false
                    },
                    {
                        label: 'Planned production = demand x ' + data.bufferRate,
                        data: data.planned,
                        borderColor: palette.planned,
                        backgroundColor: palette.plannedFill,
                        tension: 0.25,
                        pointRadius: 3,
                        fill: false
                    },
                    {
                        label: 'Actual production (units)',
                        data: data.actual,
                        borderColor: palette.actual,
                        backgroundColor: palette.actualFill,
                        tension: 0.25,
                        pointRadius: 3,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {mode: 'index', intersect: false},
                plugins: {
                    legend: {position: 'bottom'},
                    title: {display: false}
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {display: true, text: 'units'},
                        grid: {color: palette.grid}
                    },
                    x: {
                        title: {display: true, text: 'production date'},
                        grid: {display: false}
                    }
                }
            }
        });
    }

    /* -----------------------------------------------------------------
     * 2. FLOUR USAGE (bar chart)
     * ----------------------------------------------------------------- */
    var flourCanvas = document.getElementById('flourChart');
    if (flourCanvas) {
        flourChart = new Chart(flourCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Flour used (kg)',
                    data: data.flour,
                    backgroundColor: palette.bar,               /* chocolate brown */
                    borderColor: palette.barBorder,
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {legend: {display: false}},
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {display: true, text: 'kg'},
                        grid: {color: palette.grid}
                    },
                    x: {grid: {display: false}}
                }
            }
        });
    }

    /* -----------------------------------------------------------------
     * 3. REPAINT AFTER THE ☀️/🌙 SWITCH IS USED
     * theme.js sends a "bakery:theme" event; only the colours change -
     * the data, labels and numbers stay exactly as they are.
     * ----------------------------------------------------------------- */
    window.addEventListener('bakery:theme', function () {
        palette = themePalette();
        Chart.defaults.color = palette.axisText;

        if (prodChart) {
            prodChart.data.datasets[0].borderColor = palette.demand;
            prodChart.data.datasets[0].backgroundColor = palette.demandFill;
            prodChart.data.datasets[1].borderColor = palette.planned;
            prodChart.data.datasets[1].backgroundColor = palette.plannedFill;
            prodChart.data.datasets[2].borderColor = palette.actual;
            prodChart.data.datasets[2].backgroundColor = palette.actualFill;
            prodChart.options.scales.y.grid.color = palette.grid;
            prodChart.update('none');
        }

        if (flourChart) {
            flourChart.data.datasets[0].backgroundColor = palette.bar;
            flourChart.data.datasets[0].borderColor = palette.barBorder;
            flourChart.options.scales.y.grid.color = palette.grid;
            flourChart.update('none');
        }
    });
})();
