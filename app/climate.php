<?php
// app/climate.php - Climate analysis page (Quasar UMD, Vue 3, Chart.js)
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('climate.page_title');
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
            overflow-y: auto;
        }
        .chart-block {
            margin-bottom: 10px;
        }
        .chart-title {
            padding: 2px 2px 4px;
            font-size: 13px;
            font-weight: 500;
            color: #424242;
        }
        .chart-wrap {
            position: relative;
            height: 360px;
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
            .chart-wrap { height: 280px; }
        }
    </style>
</head>
<body>
    <div id="q-app">
        <div class="page-content">
            <!-- Header info chips -->
            <div class="info-bar">
                <q-chip square color="grey-2" text-color="grey-9" icon="calendar_today" size="18px" label-size="12px">
                    <?php echo th('climate.period_analysis'); ?> {{ range.inidia }} -> {{ range.fimdia }}
                </q-chip>
                <q-chip square color="grey-2" text-color="grey-9" icon="history" size="18px" label-size="12px">
                    <?php echo th('climate.period_ref'); ?> {{ range.inidiarf }} -> {{ range.fimdiarf }}
                </q-chip>
            </div>
            <q-separator></q-separator>

            <!-- Chart section: current period, reference period, anomalies -->
            <div class="chart-section">
                <div v-for="chart in charts" :key="chart.key" class="chart-block">
                    <div class="chart-title">{{ chart.title }}</div>
                    <div class="chart-wrap">
                        <canvas :id="'chartCanvas_' + chart.key"></canvas>
                        <div v-if="loading[chart.key]" class="chart-overlay">
                            <q-spinner-dots color="primary" size="40px"></q-spinner-dots>
                            <div class="q-mt-sm text-body2"><?php echo th('climate.loading'); ?></div>
                        </div>
                        <div v-else-if="errorMessage[chart.key]" class="chart-overlay text-negative">
                            <div class="text-body2">{{ errorMessage[chart.key] }}</div>
                        </div>
                        <div v-else-if="rows[chart.key].length === 0" class="chart-overlay">
                            <div class="text-body2"><?php echo th('common.no_data'); ?></div>
                        </div>
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

        const app = Vue.createApp({
            data() {
                return {
                    charts: [
                        { key: 'current',   title: <?php echo tj('climate.chart_current'); ?> },
                        { key: 'reference', title: <?php echo tj('climate.chart_reference'); ?> },
                        { key: 'anomaly',   title: <?php echo tj('climate.chart_anomaly'); ?> }
                    ],
                    chartInstance: { current: null, reference: null, anomaly: null },
                    loading: { current: false, reference: false, anomaly: false },
                    errorMessage: { current: '', reference: '', anomaly: '' },
                    rows: { current: [], reference: [], anomaly: [] },
                    range: { inidia: '', fimdia: '', inidiarf: '', fimdiarf: '' }
                };
            },


            mounted() {
                this.range = this.computeYearRange();
                const range = this.range;

                // The three series load in parallel and fail independently, so a
                // broken period leaves the other two charts on screen.
                this.loadSeries('current', { inidia: range.inidia, fimdia: range.fimdia });
                this.loadSeries('reference', { inidia: range.inidiarf, fimdia: range.fimdiarf });
                this.loadSeries('anomaly', {
                    inidia: range.inidia,
                    fimdia: range.fimdia,
                    inidiarf: range.inidiarf,
                    fimdiarf: range.fimdiarf
                });
            },

            beforeUnmount() {
                this.charts.forEach(chart => this.destroyChart(chart.key));
            },

            methods: {
                getAuthToken() {
                    return localStorage.getItem('auth_token');
                },
                // Loads one series into rows[key]. The year parameters decide which
                // api/climate.php branch answers: inidiarf/fimdiarf select the
                // anomaly branch (current minus reference), their absence the
                // plain monthly-average branch.
                async loadSeries(key, params) {
                    this.rows[key] = [];
                    this.destroyChart(key);

                    this.loading[key] = true;
                    this.errorMessage[key] = '';

                    try {
                        let url = ecoApiUrl + 'climate.php?krd=' + encodeURIComponent(krd) + '&aoi=' + encodeURIComponent(aoi);
                        Object.keys(params).forEach(name => {
                            url += '&' + name + '=' + encodeURIComponent(params[name]);
                        });
                        const token = this.getAuthToken();
                        const response = await fetch(url, {
                            signal: AbortSignal.timeout(20000),
                            headers: token ? { Authorization: 'Bearer ' + token } : {}
                        });
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        const textData = await response.text();
                        this.rows[key] = this.parseClimatePayload(textData);
                    } catch (error) {
                        this.errorMessage[key] = <?php echo tj('common.data_failed'); ?> + error.message;
                    } finally {
                        this.loading[key] = false;
                    }

                    // Render chart after DOM is ready
                    this.$nextTick(() => {
                        this.renderChart(key);
                    });
                },

                parseClimatePayload(payload) {
                    const rawRows = typeof payload === 'string' ? JSON.parse(payload) : payload;
                    if (!Array.isArray(rawRows)) return [];
                    return rawRows
                        .map(item => this.normalizeClimateRow(item))
                        .filter(item => item !== null)
                        .sort((left, right) => left.month - right.month);
                },

                normalizeClimateRow(item) {
                    if (!item || typeof item !== 'object') return null;
                    const month = Number(item.month);
                    if (!Number.isInteger(month) || month < 1 || month > 12) return null;
                    return {
                        month: month,
                        avgTemp: this.toNumberOrNull(item.avgTemp),
                        minTemp: this.toNumberOrNull(item.minTemp),
                        maxTemp: this.toNumberOrNull(item.maxTemp),
                        precipitation: this.toNumberOrNull(item.precipitation),
                        evapotranspiration: this.toNumberOrNull(item.evapotranspiration)
                    };
                },

                toNumberOrNull(value) {
                    if (value === null || value === undefined || value === '') return null;
                    const parsed = Number(value);
                    return Number.isFinite(parsed) ? parsed : null;
                },

                renderChart(key) {
                    const rows = this.rows[key];
                    const canvas = document.getElementById('chartCanvas_' + key);
                    if (!canvas || rows.length === 0) return;

                    this.destroyChart(key);
                    const ctx = canvas.getContext('2d');
                    if (!ctx) return;

                    // Anomalies swing either side of zero, so the water axis must
                    // not be pinned to it the way absolute values are.
                    const isAnomaly = (key === 'anomaly');

                    const labels = rows.map(r => this.monthLabel(r.month));
                    const precipData = rows.map(r => r.precipitation);
                    const evapData = rows.map(r => r.evapotranspiration);
                    const avgTempData = rows.map(r => r.avgTemp);
                    const minTempData = rows.map(r => r.minTemp);
                    const maxTempData = rows.map(r => r.maxTemp);

                    try {
                        this.chartInstance[key] = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        type: 'bar',
                                        label: <?php echo tj('climate.prc'); ?>,
                                        data: precipData,
                                        yAxisID: 'yWater',
                                        backgroundColor: 'rgba(13, 71, 161, 0.35)',
                                        borderColor: 'rgba(13, 71, 161, 0.7)',
                                        borderWidth: 1,
                                        barPercentage: 0.9,
                                        categoryPercentage: 0.9
                                    },
                                    {
                                        type: 'bar',
                                        label: <?php echo tj('climate.etp'); ?>,
                                        data: evapData,
                                        yAxisID: 'yWater',
                                        backgroundColor: 'rgba(239, 108, 0, 0.35)',
                                        borderColor: 'rgba(239, 108, 0, 0.7)',
                                        borderWidth: 1,
                                        barPercentage: 0.9,
                                        categoryPercentage: 0.9
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('climate.tme'); ?>,
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
                                        label: <?php echo tj('climate.tmi'); ?>,
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
                                        label: <?php echo tj('climate.tmx'); ?>,
                                        data: maxTempData,
                                        yAxisID: 'yTemp',
                                        borderColor: '#c62828',
                                        backgroundColor: 'rgba(198, 40, 40, 0.2)',
                                        borderWidth: 1.3,
                                        pointRadius: 2,
                                        spanGaps: true,
                                        tension: 0.18
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
                                            autoSkip: false
                                        },
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.07)'
                                        }
                                    },
                                    yWater: {
                                        type: 'linear',
                                        position: 'left',
                                        beginAtZero: !isAnomaly,
                                        title: {
                                            display: true,
                                            text: <?php echo tj('climate.axis_water'); ?>
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
                                            text: <?php echo tj('climate.axis_temp'); ?>
                                        },
                                        grid: {
                                            drawOnChartArea: false
                                        }
                                    }
                                }
                            }
                        });
                    } catch (error) {
                        console.error('Error rendering climate chart:', error);
                        this.chartInstance[key] = null;
                    }
                },

                destroyChart(key) {
                    if (this.chartInstance[key]) {
                        try { this.chartInstance[key].destroy(); } catch (e) { console.warn('Destroy chart error:', e); }
                        this.chartInstance[key] = null;
                    }
                },

                // Current period: the last 30 closed years. Reference: the 30 years
                // immediately before it, so the two never overlap.
                computeYearRange() {
                    const currentYear = new Date().getFullYear();
                    return {
                        inidia: String(currentYear - 30),
                        fimdia: String(currentYear - 1),
                        inidiarf: String(currentYear - 60),
                        fimdiarf: String(currentYear - 31)
                    };
                },

                monthLabel(monthNumber) {
                    const date = new Date(2001, monthNumber - 1, 1);
                    return date.toLocaleString(<?php echo json_encode(lang_locale()); ?>, { month: 'short' });
                }
            }
        });

        app.use(Quasar);
        const vm = app.mount('#q-app');

        window.climateApi = {
            getRows: function(key) { return vm.rows[key || 'current']; },
            getKrd: function() { return krd; },
            getAoi: function() { return aoi; }
        };
    })();
    </script>
</body>
</html>