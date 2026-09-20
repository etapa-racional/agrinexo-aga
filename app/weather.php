<?php
// app/weather.php - Weather analysis page (Quasar UMD, Vue 3, Chart.js)
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('weather.page_title');
$krd = isset($_GET['krd']) ? $_GET['krd'] : '';
$aoi = isset($_GET['aoi']) ? $_GET['aoi'] : '';
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">
<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; font-family: Roboto, sans-serif; background: #FAFAFA; }
        .page-content {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100%;
            padding: 12px;
            background: #FAFAFA;
        }
        .info-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 8px 12px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 8px;
        }
        .chart-section {
            flex: 1;
            min-height: 0;
        }
        .chart-wrap {
            position: relative;
            height: 100%;
            min-height: 420px;
        }
        .chart-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.72);
            text-align: center;
            z-index: 10;
        }
        @media (max-width: 700px) {
            .page-content { padding: 8px; }
            .chart-wrap { min-height: 320px; }
        }
    </style>
</head>
<body>
    <div id="q-app">
        <div class="page-content">
            <!-- Header info chips -->
            <div class="info-bar">
                <q-chip square color="grey-2" text-color="grey-9" icon="calendar_today" size="18px" label-size="12px">
                    <?php echo th('common.period'); ?> {{ range.ini }} -> {{ range.fim }}
                </q-chip>
            </div>
            <q-separator></q-separator>

            <!-- Chart section -->
            <div class="chart-section">
                <div class="chart-wrap">
                    <canvas id="chartCanvas"></canvas>
                    <div v-if="loading" class="chart-overlay">
                        <q-spinner-dots color="primary" size="40px"></q-spinner-dots>
                        <div class="q-mt-sm text-body2"><?php echo th('weather.loading'); ?></div>
                    </div>
                    <div v-else-if="errorMessage" class="chart-overlay text-negative">
                        <div class="text-body2">{{ errorMessage }}</div>
                    </div>
                    <div v-else-if="rows.length === 0" class="chart-overlay">
                        <div class="text-body2"><?php echo th('common.no_data'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

    <script>
    (function() {
        const krd = '<?php echo addslashes($krd); ?>';
        const aoi = '<?php echo addslashes($aoi); ?>';
        const ecoApiUrl = '<?php echo ECO_API_URL; ?>';

        if (!krd) {
            document.getElementById('q-app').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;"><p class="text-negative"><?php echo th('common.krd_required'); ?></p></div>';
            return;
        }

        function computeDateRange() {
            var today = new Date();
            var twentyDaysAgo = new Date(today);
            var tenDaysAhead = new Date(today);
            twentyDaysAgo.setDate(today.getDate() - 20);
            tenDaysAhead.setDate(today.getDate() + 10);

            return {
                ini: formatDate(twentyDaysAgo),
                fim: formatDate(tenDaysAhead)
            };
        }

        function formatDate(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        var app = Vue.createApp({
            data() {
                return {
                    chartInstance: null,
                    loading: false,
                    errorMessage: '',
                    rows: [],
                    range: computeDateRange(),
                    loadedFieldId: ''
                };
            },


            mounted() {
                var targetFieldId = krd + ':' + aoi;
                this.range = computeDateRange();
                this.loading = true;
                this.errorMessage = '';
                this.loadWeatherData(targetFieldId);
            },

            beforeUnmount() {
                this.destroyChart();
            },

            methods: {
                getAuthToken() {
                    return localStorage.getItem('auth_token');
                },
                async loadWeatherData(targetFieldId) {
                    this.rows = [];
                    this.loadedFieldId = '';
                    this.destroyChart();

                    this.loading = true;
                    this.errorMessage = '';

                    try {
                        const url = ecoApiUrl + 'weather.php?krd=' + encodeURIComponent(krd) + '&aoi=' + encodeURIComponent(aoi) + '&ini=' + encodeURIComponent(this.range.ini) + '&fim=' + encodeURIComponent(this.range.fim);
                        const token = this.getAuthToken();
                        const response = await fetch(url, {
                            signal: AbortSignal.timeout(20000),
                            headers: token ? { Authorization: 'Bearer ' + token } : {}
                        });
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        const textData = await response.text();
                        this.rows = this.parseWeatherPayload(textData);
                        this.loadedFieldId = targetFieldId;
                    } catch (error) {
                        this.errorMessage = <?php echo tj('common.data_failed'); ?> + error.message;
                    } finally {
                        this.loading = false;
                    }

                    // Render chart after DOM is ready
                    this.$nextTick(() => {
                        this.renderChart();
                    });
                },

                parseWeatherPayload(payload) {
                    const rawRows = typeof payload === 'string' ? JSON.parse(payload) : payload;
                    if (!Array.isArray(rawRows)) return [];
                    return rawRows
                        .map(item => this.normalizeWeatherRow(item))
                        .filter(item => item !== null)
                        .sort((left, right) => left.date.localeCompare(right.date));
                },

                normalizeWeatherRow(item) {
                    if (!item || typeof item !== 'object') return null;
                    var date = String(item.date || '').trim();
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return null;
                    return {
                        date: date,
                        avgTemp: this.toNumberOrNull(item.avgTemp),
                        minTemp: this.toNumberOrNull(item.minTemp),
                        maxTemp: this.toNumberOrNull(item.maxTemp),
                        precipitation: this.toNumberOrNull(item.precipitation),
                        evapotranspiration: this.toNumberOrNull(item.evapotranspiration),
                        cropEvapotranspiration: this.toNumberOrNull(item.cropEvatranspiration),
                        ndvi: this.toNumberOrNull(item.ndvi),
                        kcb: this.toNumberOrNull(item.cropBasalCoeficient)
                    };
                },

                toNumberOrNull(value) {
                    if (value === null || value === undefined || value === '') return null;
                    var parsed = Number(value);
                    return Number.isFinite(parsed) ? parsed : null;
                },

                renderChart() {
                    var canvas = document.getElementById('chartCanvas');
                    if (!canvas || this.rows.length === 0) return;

                    this.destroyChart();
                    var ctx = canvas.getContext('2d');
                    if (!ctx) return;

                    var self = this;
                    var labels = this.rows.map(function(r) { return r.date; });
                    var precipData = this.rows.map(function(r) { return r.precipitation; });
                    var evapData = this.rows.map(function(r) { return r.evapotranspiration; });
                    var cropEvapData = this.rows.map(function(r) { return r.cropEvapotranspiration; });
                    var avgTempData = this.rows.map(function(r) { return r.avgTemp; });
                    var minTempData = this.rows.map(function(r) { return r.minTemp; });
                    var maxTempData = this.rows.map(function(r) { return r.maxTemp; });
                    var ndviData = this.rows.map(function(r) { return r.ndvi; });
                    var kcbData = this.rows.map(function(r) { return r.kcb; });

                    // Dotted vertical line at today, when today is inside the range.
                    var todayLine = {
                        id: 'todayLine',
                        afterDatasetsDraw: function(chart) {
                            var d = new Date();
                            var today = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0')
                                + '-' + String(d.getDate()).padStart(2, '0');
                            var index = chart.data.labels.indexOf(today);
                            if (index < 0) return;
                            var x = chart.scales.x.getPixelForValue(index);
                            var area = chart.chartArea;
                            var c = chart.ctx;
                            c.save();
                            c.beginPath();
                            c.setLineDash([4, 4]);
                            c.lineWidth = 1.5;
                            c.strokeStyle = 'rgba(0, 0, 0, 0.6)';
                            c.moveTo(x, area.top);
                            c.lineTo(x, area.bottom);
                            c.stroke();
                            c.restore();
                        }
                    };

                    try {
                        this.chartInstance = new Chart(ctx, {
                            type: 'line',
                            plugins: [todayLine],
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        type: 'bar',
                                        label: <?php echo tj('chart.precipitation'); ?>,
                                        data: precipData,
                                        yAxisID: 'yWater',
                                        backgroundColor: 'rgba(13, 71, 161, 0.35)',
                                        borderColor: 'rgba(13, 71, 161, 0.7)',
                                        borderWidth: 1,
                                        barPercentage: 0.9,
                                        categoryPercentage: 0.9
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('chart.evapotranspiration'); ?>,
                                        data: evapData,
                                        yAxisID: 'yWater',
                                        borderColor: '#ef6c00',
                                        backgroundColor: 'rgba(239, 108, 0, 0.2)',
                                        borderWidth: 1.6,
                                        pointRadius: 2,
                                        spanGaps: true,
                                        tension: 0.2
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('chart.crop_evap'); ?>,
                                        data: cropEvapData,
                                        yAxisID: 'yWater',
                                        borderColor: '#6a1b9a',
                                        backgroundColor: 'rgba(106, 27, 154, 0.2)',
                                        borderWidth: 1.6,
                                        pointRadius: 2,
                                        spanGaps: true,
                                        tension: 0.2
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('chart.temp_mean'); ?>,
                                        data: avgTempData,
                                        yAxisID: 'yTemp',
                                        borderColor: '#000000',
                                        backgroundColor: 'rgba(0, 0, 0, 0.2)',
                                        borderWidth: 1.8,
                                        pointRadius: 2,
                                        spanGaps: true,
                                        tension: 0.2
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('chart.temp_min'); ?>,
                                        data: minTempData,
                                        yAxisID: 'yTemp',
                                        borderColor: '#546e7a',
                                        backgroundColor: 'rgba(84, 110, 122, 0.2)',
                                        borderWidth: 1.3,
                                        pointRadius: 2,
                                        spanGaps: true,
                                        tension: 0.18
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('chart.temp_max'); ?>,
                                        data: maxTempData,
                                        yAxisID: 'yTemp',
                                        borderColor: '#c62828',
                                        backgroundColor: 'rgba(198, 40, 40, 0.2)',
                                        borderWidth: 1.3,
                                        pointRadius: 2,
                                        spanGaps: true,
                                        tension: 0.18
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('chart.ndvi'); ?>,
                                        data: ndviData,
                                        hidden: true,
                                        yAxisID: 'yIndex',
                                        borderColor: '#2e7d32',
                                        backgroundColor: 'rgba(46, 125, 50, 0.2)',
                                        borderWidth: 2,
                                        pointRadius: 2,
                                        spanGaps: true,
                                        tension: 0.2
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('water.kcb'); ?>,
                                        data: kcbData,
                                        hidden: true,
                                        yAxisID: 'yIndex',
                                        borderColor: '#ad1457',
                                        backgroundColor: 'rgba(173, 20, 87, 0.2)',
                                        borderWidth: 1.5,
                                        pointRadius: 0,
                                        spanGaps: true,
                                        tension: 0.2
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: {
                                    mode: 'index',
                                    intersect: false
                                },
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                },
                                scales: {
                                    x: {
                                        ticks: {
                                            autoSkip: true,
                                            maxTicksLimit: 12
                                        },
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.07)'
                                        }
                                    },
                                    yWater: {
                                        type: 'linear',
                                        position: 'left',
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: <?php echo tj('chart.water_data'); ?>
                                        },
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.08)'
                                        }
                                    },
                                    yTemp: {
                                        type: 'linear',
                                        position: 'right',
                                        title: {
                                            display: true,
                                            text: <?php echo tj('chart.temperature_c'); ?>
                                        },
                                        grid: {
                                            drawOnChartArea: false
                                        }
                                    },
                                    yIndex: {
                                        type: 'linear',
                                        display: 'auto',
                                        position: 'left',
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: <?php echo tj('water.axis_index'); ?>
                                        },
                                        grid: {
                                            drawOnChartArea: false
                                        }
                                    }
                                }
                            }
                        });
                    } catch (error) {
                        console.error('Error rendering weather chart:', error);
                        this.chartInstance = null;
                    }
                },

                destroyChart() {
                    if (this.chartInstance) {
                        try { this.chartInstance.destroy(); } catch (e) { console.warn('Destroy chart error:', e); }
                        this.chartInstance = null;
                    }
                }
            }
        });

        app.use(Quasar);
        app.mount('#q-app');

        window.weatherApi = {
            getRows: function() { return app._data.rows; },
            getKrd: function() { return krd; },
            getAoi: function() { return aoi; }
        };
    })();
    </script>
</body>
</html>