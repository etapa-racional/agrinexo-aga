<?php
// app/water.php - Water source analysis page (Quasar UMD, Vue 3, Chart.js)
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('water.page_title');
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
                    <?php echo th('common.period'); ?> {{ range.inidia }} -> {{ range.fimdia }}
                </q-chip>
            </div>
            <q-separator></q-separator>

            <!-- Chart section -->
            <div class="chart-section">
                <div class="chart-wrap">
                    <canvas id="chartCanvas"></canvas>
                    <div v-if="loading" class="chart-overlay">
                        <q-spinner-dots color="primary" size="40px"></q-spinner-dots>
                        <div class="q-mt-sm text-body2"><?php echo th('water.loading'); ?></div>
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
        const WATER_SOURCE_URL = '<?php echo ECO_API_URL; ?>water.php';

        if (!krd) {
            document.getElementById('q-app').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;"><p class="text-negative"><?php echo th('common.krd_required'); ?></p></div>';
            return;
        }

        const app = Vue.createApp({
            data() {
                return {
                    chartInstance: null,
                    loading: false,
                    errorMessage: '',
                    rows: [],
                    range: { inidia: '', fimdia: '' },
                    loadedFieldId: ''
                };
            },

            watch: {
                async 'range'(newVal, oldVal) {
                    // Trigger reload when range changes (if needed externally)
                }
            },

            mounted() {
                const targetFieldId = krd + ':' + aoi;
                this.range = this.computeDateRange();
                this.loading = true;
                this.errorMessage = '';
                this.loadedFieldId = '';

                this.loadWaterData(targetFieldId);
            },

            beforeUnmount() {
                this.destroyChart();
            },

            methods: {
                getAuthToken() {
                    return localStorage.getItem('auth_token');
                },
                async loadWaterData(targetFieldId) {
                    if (this.rows.length > 0 && this.loadedFieldId === targetFieldId) {
                        this.$nextTick(() => {
                            this.renderChart();
                        });
                        return;
                    }

                    this.rows = [];
                    this.loadedFieldId = '';
                    this.destroyChart();

                    this.loading = true;
                    this.errorMessage = '';

                    try {
                        const url = WATER_SOURCE_URL + '?krd=' + encodeURIComponent(krd) + '&aoi=' + encodeURIComponent(aoi) + '&inidia=' + encodeURIComponent(this.range.inidia) + '&fimdia=' + encodeURIComponent(this.range.fimdia);
                        const token = this.getAuthToken();
                        const response = await fetch(url, {
                            signal: AbortSignal.timeout(20000),
                            headers: token ? { Authorization: 'Bearer ' + token } : {}
                        });
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        const textData = await response.text();
                        this.rows = this.parseWaterPayload(textData);
                        this.loadedFieldId = targetFieldId;
                    } catch (error) {
                        this.errorMessage = <?php echo tj('common.data_failed'); ?> + (error.response?.data?.message || error.message);
                    } finally {
                        this.loading = false;
                    }

                    // Render chart after DOM is ready
                    this.$nextTick(() => {
                        this.renderChart();
                    });
                },

                reload() {
                    const targetFieldId = krd + ':' + aoi;
                    this.range = this.computeDateRange();
                    this.loadWaterData(targetFieldId);
                },

                parseWaterPayload(payload) {
                    const rawRows = typeof payload === 'string' ? JSON.parse(payload) : payload;
                    if (!Array.isArray(rawRows)) return [];
                    return rawRows
                        .map(item => this.normalizeWaterRow(item))
                        .filter(item => item !== null)
                        .sort((left, right) => left.date.localeCompare(right.date));
                },

                normalizeWaterRow(item) {
                    if (!item || typeof item !== 'object') return null;
                    const date = String(item.date ?? '').trim();
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return null;
                    return {
                        date,
                        precipitation: this.toNumberOrNull(item.precipitation),
                        etp: this.toNumberOrNull(item.etp),
                        etb: this.toNumberOrNull(item.etb),
                        etr: this.toNumberOrNull(item.etr),
                        iws: this.toNumberOrNull(item.iws),
                        irr: this.toNumberOrNull(item.irr),
                        saw: this.toNumberOrNull(item.saw),
                        wra: this.toNumberOrNull(item.wra),
                        taw: this.toNumberOrNull(item.taw),
                        wrd: this.toNumberOrNull(item.wrd),
                        kcb: this.toNumberOrNull(item.kcb),
                        ndvi: this.toNumberOrNull(item.ndvi)
                    };
                },

                toNumberOrNull(value) {
                    if (value === null || value === undefined || value === '') return null;
                    const parsed = Number(value);
                    return Number.isFinite(parsed) ? parsed : null;
                },

                renderChart() {
                    const canvas = document.getElementById('chartCanvas');
                    if (!canvas || this.rows.length === 0) return;

                    this.destroyChart();
                    const ctx = canvas.getContext('2d');
                    if (!ctx) return;

                    const labels = this.rows.map(r => r.date);
                    const sawData = this.rows.map(r => r.saw);
                    const tawData = this.rows.map(r => r.taw);
                    const wraData = this.rows.map(r => r.wra);
                    const irrigationData = this.rows.map(r => r.irr);
                    const precipitationData = this.rows.map(r => r.precipitation);
                    const wrdData = this.rows.map(r => r.wrd);
                    const etbData = this.rows.map(r => r.etb);
                    const etrData = this.rows.map(r => r.etr);
                    const etpData = this.rows.map(r => r.etp);
                    const ndviData = this.rows.map(r => r.ndvi);
                    const kcbData = this.rows.map(r => r.kcb);

                    try {
                        this.chartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        type: 'line',
                                        label: <?php echo tj('water.saw'); ?>,
                                        data: sawData,
                                        yAxisID: 'yStore',
                                        borderColor: '#00897b',
                                        backgroundColor: 'rgba(0, 137, 123, 0.2)',
                                        borderWidth: 2,
                                        pointRadius: 0,
                                        spanGaps: true,
                                        tension: 0.2
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('water.taw'); ?>,
                                        data: tawData,
                                        yAxisID: 'yStore',
                                        borderColor: '#455a64',
                                        backgroundColor: 'rgba(69, 90, 100, 0.2)',
                                        borderWidth: 1.2,
                                        borderDash: [5, 4],
                                        pointRadius: 0,
                                        spanGaps: true,
                                        tension: 0.2
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('water.wra'); ?>,
                                        data: wraData,
                                        yAxisID: 'yStore',
                                        borderColor: '#8d6e63',
                                        backgroundColor: 'rgba(141, 110, 99, 0.2)',
                                        borderWidth: 1.2,
                                        borderDash: [3, 3],
                                        pointRadius: 0,
                                        spanGaps: true,
                                        tension: 0.2
                                    },
                                    {
                                        type: 'bar',
                                        label: <?php echo tj('chart.irrigation'); ?>,
                                        data: irrigationData,
                                        hidden: true,
                                        yAxisID: 'yFlux',
                                        backgroundColor: 'rgba(67, 160, 71, 0.33)',
                                        borderColor: 'rgba(67, 160, 71, 0.75)',
                                        borderWidth: 1,
                                        barPercentage: 1.0,
                                        categoryPercentage: 1.0
                                    },
                                    {
                                        type: 'bar',
                                        label: <?php echo tj('chart.precipitation'); ?>,
                                        data: precipitationData,
                                        hidden: true,
                                        yAxisID: 'yFlux',
                                        backgroundColor: 'rgba(13, 71, 161, 0.33)',
                                        borderColor: 'rgba(13, 71, 161, 0.75)',
                                        borderWidth: 1,
                                        barPercentage: 1.0,
                                        categoryPercentage: 1.0
                                    },
                                    {
                                        type: 'bar',
                                        label: <?php echo tj('water.wrd'); ?>,
                                        data: wrdData,
                                        hidden: true,
                                        yAxisID: 'yFlux',
                                        backgroundColor: 'rgba(255, 179, 0, 0.33)',
                                        borderColor: 'rgba(255, 179, 0, 0.75)',
                                        borderWidth: 1,
                                        barPercentage: 1.0,
                                        categoryPercentage: 1.0
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('water.etb'); ?>,
                                        data: etbData,
                                        hidden: true,
                                        yAxisID: 'yFlux',
                                        borderColor: '#6a1b9a',
                                        backgroundColor: 'rgba(106, 27, 154, 0.2)',
                                        borderWidth: 1.5,
                                        pointRadius: 0,
                                        spanGaps: true,
                                        tension: 0.2
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('water.etr'); ?>,
                                        data: etrData,
                                        hidden: true,
                                        yAxisID: 'yFlux',
                                        borderColor: '#ab47bc',
                                        backgroundColor: 'rgba(171, 71, 188, 0.2)',
                                        borderWidth: 1.5,
                                        pointRadius: 0,
                                        spanGaps: true,
                                        tension: 0.2
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('chart.evapotranspiration'); ?>,
                                        data: etpData,
                                        hidden: true,
                                        yAxisID: 'yFlux',
                                        borderColor: '#ef6c00',
                                        backgroundColor: 'rgba(239, 108, 0, 0.2)',
                                        borderWidth: 1.5,
                                        pointRadius: 0,
                                        spanGaps: true,
                                        tension: 0.2
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
                                // Storage is on by default and owns the grid; the flux and
                                // index axes appear only while one of their series is on.
                                scales: {
                                    x: {
                                        ticks: {
                                            autoSkip: true,
                                            maxTicksLimit: 14
                                        },
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.07)'
                                        }
                                    },
                                    yStore: {
                                        type: 'linear',
                                        position: 'left',
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: <?php echo tj('water.axis_store'); ?>
                                        },
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.08)'
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
                                    },
                                    yFlux: {
                                        type: 'linear',
                                        display: 'auto',
                                        position: 'right',
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: <?php echo tj('water.axis_flux'); ?>
                                        },
                                        grid: {
                                            drawOnChartArea: false
                                        }
                                    }
                                }
                            }
                        });
                    } catch (error) {
                        console.error('Error rendering water chart:', error);
                        this.chartInstance = null;
                    }
                },

                destroyChart() {
                    if (this.chartInstance) {
                        try { this.chartInstance.destroy(); } catch (e) { console.warn('Destroy chart error:', e); }
                        this.chartInstance = null;
                    }
                },

                computeDateRange() {
                    const today = new Date();
                    const pastDate = new Date(today);
                    const futureDate = new Date(today);
                    pastDate.setDate(today.getDate() - 180);
                    futureDate.setDate(today.getDate() + 180);

                    return {
                        inidia: this.formatDate(pastDate),
                        fimdia: this.formatDate(futureDate)
                    };
                },

                formatDate(date) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return year + '-' + month + '-' + day;
                }
            }
        });

        app.use(Quasar);
        app.mount('#q-app');

        // Expose API for the parent modal to use
        window.waterApi = {
            getRows: function() { return app._data.rows; },
            getKrd: function() { return krd; },
            getAoi: function() { return aoi; },
            reload: function() { app._data.loadWaterData(krd + ':' + aoi); }
        };
    })();
    </script>
</body>
</html>