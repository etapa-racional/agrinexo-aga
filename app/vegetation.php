<?php require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('vegetation.page_title'); ?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">
<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; font-family: Roboto, sans-serif; background: #FAFAFA; }
        .page-content {
            display: flex;
            flex-direction: column;
            height: 100vh;
            min-height: 800px;
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
            flex: 1.1;
            min-height: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 12px;
            margin-bottom: 8px;
            display: flex;
            flex-direction: column;
        }
        .chart-wrap {
            flex: 1;
            position: relative;
            min-height: 280px;
        }
        .chart-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.85);
            text-align: center;
            z-index: 10;
        }
        .map-section {
            flex: 1;
            min-height: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 12px;
            display: flex;
            flex-direction: column;
        }
        .map-title {
            font-size: 16px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        .ndvi-map-wrap {
            flex: 1;
            position: relative;
            min-height: 220px;
        }
        .ndvi-map {
            width: 100%;
            height: 100%;
            border-radius: 4px;
        }
        .map-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: rgba(255, 255, 255, 0.85);
            border-radius: 4px;
            z-index: 10;
            /* Never let a status overlay (loading/error) block dragging/clicking the
               map underneath it; overlays only display informational content. */
            pointer-events: none;
        }
        .ndvi-hover-tooltip {
            position: absolute;
            z-index: 1200;
            pointer-events: none;
            background: rgba(0, 0, 0, 0.75);
            color: #fff;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }
        .ndvi-legend {
            position: absolute;
            right: 10px;
            bottom: 10px;
            z-index: 450;
            min-width: 130px;
            max-width: 180px;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid rgba(0, 0, 0, 0.12);
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
            font-size: 11px;
            line-height: 1.2;
        }
        .ndvi-legend-title { font-weight: 600; margin-bottom: 6px; }
        .ndvi-legend-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 3px;
        }
        .ndvi-legend-row:last-child { margin-bottom: 0; }
        .ndvi-legend-swatch {
            width: 12px;
            height: 12px;
            border: 1px solid rgba(0, 0, 0, 0.25);
            border-radius: 2px;
            flex: 0 0 12px;
        }
        .btn-veg {
            background: #87B55F;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-veg:hover { background: #7AA34E; }
        @media (max-width: 700px) {
            .chart-wrap { min-height: 240px; }
            .ndvi-map-wrap { min-height: 180px; }
        }
    </style>
</head>
<body>
    <div id="q-app">
        <div class="page-content">
            <!-- Header info chips -->
                <div class="info-bar">
                    <q-chip square color="grey-2" text-color="grey-9" icon="calendar_today" size="18px" label-size="12px"><?php echo th('common.period'); ?> {{ rangeDisplay }}</q-chip>
                </div>

            <!-- Chart section -->
            <div class="chart-section">
                <div class="chart-wrap">
                    <canvas id="chartCanvas"></canvas>
                    <div v-if="loading" class="chart-overlay">
                        <q-spinner-dots color="primary" size="40px"></q-spinner-dots>
                        <div class="q-mt-sm text-body2"><?php echo th('vegetation.loading'); ?></div>
                    </div>
                    <div v-else-if="errorMessage" class="chart-overlay text-negative">
                        <q-icon name="error" size="48px"></q-icon>
                        <div class="q-mt-sm text-body1">{{ errorMessage }}</div>
                    </div>
                    <div v-else-if="rows.length === 0" class="chart-overlay">
                        <q-icon name="info" size="48px" color="grey-7"></q-icon>
                        <div class="q-mt-sm text-body1"><?php echo th('common.no_data'); ?></div>
                    </div>
                </div>
            </div>

            <!-- NDVI Map section -->
            <div class="map-section">
                <div class="row items-center justify-between q-gutter-sm q-mb-sm">
                    <div class="text-subtitle1"><?php echo th('vegetation.map_title'); ?></div>
                    <div class="text-caption text-grey-7"><?php echo th('vegetation.map_hint'); ?></div>
                </div>
                <div class="ndvi-map-wrap">
                    <div id="ndviMapElement" class="ndvi-map"></div>
                    <div v-if="showNdviLegend" class="ndvi-legend">
                        <div class="ndvi-legend-title">{{ ndviLegendTitle }}</div>
                        <div v-for="entry in ndviLegendItems" :key="entry.label" class="ndvi-legend-row">
                            <span class="ndvi-legend-swatch" :style="{ backgroundColor: entry.color }"></span>
                            <span>{{ entry.label }}</span>
                        </div>
                    </div>
                    <div v-if="ndviMapLoading" class="map-overlay">
                        <q-spinner-dots color="primary" size="34px"></q-spinner-dots>
                        <div class="q-mt-sm text-body2"><?php echo th('vegetation.map_loading'); ?></div>
                    </div>
                    <div v-else-if="ndviMapErrorMessage" class="map-overlay text-negative">
                        <q-icon name="error" size="36px"></q-icon>
                        <div class="q-mt-sm text-body2">{{ ndviMapErrorMessage }}</div>
                    </div>
                    <div v-else-if="selectedNdviFileId === ''" class="map-overlay">
                        <q-icon name="map" size="36px" color="grey-7"></q-icon>
                        <div class="q-mt-sm text-body2"><?php echo th('vegetation.select_point'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    <!-- GeoRaster + GeoBlaze for GeoTIFF rendering (UMD global scripts) -->
    <script src="../libs/georaster.js"></script>
    <script src="../libs/georaster-layer-for-leaflet.js"></script>
    <script src="../libs/geoblaze.js"></script>

    <script>
    (function() {
        // Capture global exports from UMD builds
        const GeoRaster = window.GeoRaster;
        const GeoRasterLayer = window.GeoRasterLayer || (window.GeoRaster && GeoRaster.Layer) || null;
        const geoblazeLib = window.geoblaze || null;

        // Make libs available to Vue methods via window
        window.__vegLibs = { GeoRaster, GeoRasterLayer, geoblaze: geoblazeLib };

        const params = new URLSearchParams(window.location.search);
        const krd = params.get('krd') || '';
        const aoi = params.get('aoi') || params.get('map') || '';
        const ecoApiUrl = '<?php echo ECO_API_URL; ?>';

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
                    range: { ini: '', fim: '' },
                    loadedFieldId: '',
                    ndviMapInstance: null,
                    ndviMapLoading: false,
                    ndviMapErrorMessage: '',
                    selectedNdviDate: '',
                    selectedNdviValue: null,
                    selectedNdviFileId: '',
                    ndviGeoRasterLayer: null,
                    ndviMouseMoveHandler: null,
                    ndviMouseOutHandler: null,
                    ndviTooltipEl: null,
                    ndviGeoRaster: null,
                    ndviGeoJsonLayer: null
                };
            },

            computed: {
                rangeDisplay() {
                    return this.range.ini + ' → ' + this.range.fim;
                },
                showNdviLegend() {
                    return this.selectedNdviFileId !== '' && !this.ndviMapLoading && this.ndviMapErrorMessage === '';
                },
                ndviLegendTitle() {
                    return this.selectedNdviDate !== '' ? this.selectedNdviDate : 'Legenda NDVI';
                },
                ndviLegendItems() {
                    return [
                        { label: '>= 0.80', color: '#1b5e20' },
                        { label: '0.60 - 0.79', color: '#43a047' },
                        { label: '0.40 - 0.59', color: '#8bc34a' },
                        { label: '0.20 - 0.39', color: '#fdd835' },
                        { label: '< 0.20', color: '#e53935' },
                        { label: <?php echo tj('vegetation.legend_no_data'); ?>, color: '#9e9e9e' }
                    ];
                }
            },

            mounted() {
                console.log('[Vegetation] Vue mounted, krd:', krd, 'aoi:', aoi);
                this.range = this.computeDateRange();
                this.$nextTick(() => {
                    this.loadVegetationData();
                });
            },

            beforeUnmount() {
                this.destroyChart();
                this.destroyMap();
            },

            methods: {
                getAuthToken() {
                    return localStorage.getItem('auth_token');
                },
                async loadVegetationData() {
                    const targetFieldId = `${krd}:${aoi}`;

                    if (this.rows.length > 0 && this.loadedFieldId === targetFieldId) {
                        this.loading = false;
                        return;
                    }

                    this.rows = [];
                    this.loadedFieldId = '';
                    this.destroyChart();
                    this.destroyMap();

                    this.loading = true;
                    this.errorMessage = '';
                    try {
                        const url = `${ecoApiUrl}vegetation.php?krd=${encodeURIComponent(krd)}&aoi=${encodeURIComponent(aoi)}&ini=${encodeURIComponent(this.range.ini)}&fim=${encodeURIComponent(this.range.fim)}`;
                        console.log('[Vegetation] Fetching:', url);
                        const token = this.getAuthToken();
                        const response = await fetch(url, {
                            signal: AbortSignal.timeout(20000),
                            headers: token ? { Authorization: 'Bearer ' + token } : {}
                        });
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        const textData = await response.text();
                        console.log('[Vegetation] Raw response:', textData.substring(0, 200));
                        this.rows = this.parseVegetationPayload(textData);
                        this.loadedFieldId = targetFieldId;
                        console.log('[Vegetation] Parsed rows:', this.rows.length);
                    } catch (error) {
                        this.errorMessage = <?php echo tj('common.data_failed'); ?> + error.message;
                        console.error('[Vegetation] Error loading data:', error);
                    } finally {
                        this.loading = false;
                    }

                    // DOM elements exist immediately — same pattern as your working page
                    this.$nextTick(() => {
                        console.log('[Vegetation] Rendering chart, canvas:', document.getElementById('chartCanvas'));
                        this.renderChart();
                        if (this.rows.length > 0) {
                            this.loadLatestAvailableNdviMap();
                        }
                    });
                },

                parseVegetationPayload(payload) {
                    const rawRows = typeof payload === 'string' ? JSON.parse(payload) : payload;
                    if (!Array.isArray(rawRows)) return [];
                    return rawRows
                        .map(item => this.normalizeVegetationRow(item))
                        .filter(item => item !== null)
                        .sort((a, b) => a.date.localeCompare(b.date));
                },

                normalizeVegetationRow(item) {
                    if (!item || typeof item !== 'object') return null;
                    const date = String(item.date ?? '').trim();
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return null;
                    return {
                        date,
                        avgTemp: this.toNumberOrNull(item.avgTemp),
                        precipitation: this.toNumberOrNull(item.precipitation),
                        evapotranspiration: this.toNumberOrNull(item.evapotranspiration),
                        ndvi: this.toNumberOrNull(item.ndvi),
                        fileId: this.resolveNdviFileId(item)
                    };
                },

                resolveNdviFileId(item) {
                    const candidates = [item.fileId, item.fileID, item.file_id, item.json, item.jsonId, item.geojsonId];
                    for (const c of candidates) {
                        const n = String(c ?? '').trim();
                        if (n !== '' && n.toLowerCase() !== 'null') return n;
                    }
                    return '';
                },

                toNumberOrNull(value) {
                    if (value === null || value === undefined || value === '') return null;
                    const parsed = Number(value);
                    return Number.isFinite(parsed) ? parsed : null;
                },

                renderChart() {
                    if (!this.rows || this.rows.length === 0) return;

                    const chartCanvas = document.getElementById('chartCanvas');
                    if (!chartCanvas) {
                        console.warn('[Vegetation] renderChart: canvas not found');
                        return;
                    }

                    console.log('[Vegetation] Canvas found:', chartCanvas, 'dimensions:', chartCanvas.width, 'x', chartCanvas.height);
                    this.destroyChart();

                    const ctx = chartCanvas.getContext('2d');
                    if (!ctx) return;

                    const labels = this.rows.map(r => r.date);
                    const avgTempData = this.rows.map(r => r.avgTemp);
                    const precipData = this.rows.map(r => r.precipitation);
                    const evapData = this.rows.map(r => r.evapotranspiration);
                    const ndviData = this.rows.map(r => r.ndvi);
                    const ndviRadius = this.rows.map(r => r.ndvi === null ? 0 : 3);

                    try {
                        this.chartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels,
                                datasets: [
                                    {
                                        type: 'line',
                                        label: <?php echo tj('chart.ndvi'); ?>,
                                        data: ndviData,
                                        yAxisID: 'yNdvi',
                                        borderColor: '#2e7d32',
                                        backgroundColor: 'rgba(46, 125, 50, 0.2)',
                                        borderWidth: 2,
                                        pointRadius: ndviRadius,
                                        pointHoverRadius: 5,
                                        pointHitRadius: 8,
                                        spanGaps: false,
                                        tension: 0.22
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('chart.temp_mean'); ?>,
                                        data: avgTempData,
                                        yAxisID: 'yTemp',
                                        borderColor: '#000000',
                                        backgroundColor: 'rgba(0, 0, 0, 0.2)',
                                        borderWidth: 1.8,
                                        pointRadius: 0,
                                        spanGaps: true,
                                        tension: 0.2
                                    },
                                    {
                                        type: 'bar',
                                        label: <?php echo tj('chart.precipitation'); ?>,
                                        data: precipData,
                                        yAxisID: 'yWater',
                                        backgroundColor: 'rgba(13, 71, 161, 0.35)',
                                        borderColor: 'rgba(13, 71, 161, 0.7)',
                                        borderWidth: 1,
                                        barPercentage: 1.0,
                                        categoryPercentage: 1.0
                                    },
                                    {
                                        type: 'line',
                                        label: <?php echo tj('chart.evapotranspiration'); ?>,
                                        data: evapData,
                                        yAxisID: 'yWater',
                                        borderColor: '#ef6c00',
                                        backgroundColor: 'rgba(239, 108, 0, 0.2)',
                                        borderWidth: 1.6,
                                        pointRadius: 0,
                                        spanGaps: true,
                                        tension: 0.2
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: false,
                                onClick: (_event, elements) => {
                                    if (!elements || elements.length === 0) return;
                                    const el = elements[0];
                                    const ds = this.chartInstance.data.datasets[el.datasetIndex];
                                    if (!ds || ds.yAxisID !== 'yNdvi') return;
                                    const row = this.rows[el.index];
                                    if (!row || row.ndvi === null) return;
                                    this.loadNdviGeoTiffForRow(row);
                                },
                                interaction: { mode: 'index', intersect: false },
                                plugins: { legend: { position: 'bottom' } },
                                scales: {
                                    x: {
                                        ticks: { autoSkip: true, maxticksLimit: 12, font: { size: 10 } },
                                        grid: { color: 'rgba(0,0,0,0.07)' }
                                    },
                                    yWater: {
                                        type: 'linear', position: 'right', beginAtZero: true,
                                        title: { display: true, text: <?php echo tj('chart.axis_water'); ?>, font: { size: 11 } },
                                        grid: { color: 'rgba(0,0,0,0.08)' }
                                    },
                                    yTemp: {
                                        type: 'linear', position: 'right',
                                        title: { display: true, text: <?php echo tj('chart.axis_temp'); ?>, font: { size: 11 } },
                                        grid: { drawOnChartArea: false }
                                    },
                                    yNdvi: {
                                        type: 'linear', position: 'left', min: 0, max: 1,
                                        title: { display: true, text: 'NDVI', font: { size: 11 } },
                                        grid: { drawOnChartArea: false }
                                    }
                                }
                            }
                        });
                        console.log('[Vegetation] Chart created successfully');
                    } catch (error) {
                        console.error('[Vegetation] Error rendering vegetation chart:', error);
                        this.chartInstance = null;
                    }
                },

                destroyChart() {
                    if (this.chartInstance) {
                        try { this.chartInstance.destroy(); } catch (e) { console.warn('Destroy chart error:', e); }
                        this.chartInstance = null;
                    }
                },

                initializeMap() {
                    const mapEl = document.getElementById('ndviMapElement');
                    if (!mapEl || this.ndviMapInstance) return;

                    const vertices = window.parent.VegetationModal ? window.parent.VegetationModal.getFieldVertices() : [];
                    let center = [38.7098, -9.184];
                    if (vertices && vertices.length > 0) {
                        let latSum = 0, lngSum = 0, cnt = 0;
                        for (const v of vertices) {
                            if (Array.isArray(v) && v.length >= 2) {
                                latSum += Number(v[0]); lngSum += Number(v[1]); cnt++;
                            }
                        }
                        if (cnt > 0) center = [latSum / cnt, lngSum / cnt];
                    }

                    this.ndviMapInstance = L.map(mapEl).setView(center, 14);
                    // OSM up to zoom 15, Esri World Imagery from 16.
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 15, attribution: '&copy; OpenStreetMap'
                    }).addTo(this.ndviMapInstance);
                    L.tileLayer('https://ibasemaps-api.arcgis.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}?token=' + <?php echo json_encode(ARCGIS_TOKEN); ?>, {
                        minZoom: 16, maxZoom: 19, attribution: 'Powered by Esri | Source: Esri, Maxar, Earthstar Geographics'
                    }).addTo(this.ndviMapInstance);

                    // Custom lightweight tooltip element for showing NDVI pixel values on
                    // hover. We avoid Leaflet's built-in bindTooltip/DivOverlay here: that
                    // system ties tooltip visibility to a 'zoomanim' map listener registered
                    // via addLayer/removeLayer, and rapid open/close cycles (e.g. moving the
                    // mouse in/out of the raster while switching datapoints or zooming) can
                    // leave a stale Tooltip instance whose zoomanim listener still fires after
                    // its _map reference was cleared, throwing
                    // "Cannot read properties of null (reading '_latLngToNewLayerPoint')".
                    // A plain absolutely-positioned div we manage ourselves sidesteps that
                    // lifecycle entirely.
                    this.ndviTooltipEl = document.createElement('div');
                    this.ndviTooltipEl.className = 'ndvi-hover-tooltip';
                    this.ndviTooltipEl.style.display = 'none';
                    mapEl.appendChild(this.ndviTooltipEl);

                    setTimeout(() => {
                        if (this.ndviMapInstance) this.ndviMapInstance.invalidateSize();
                    }, 100);
                },

                destroyMap() {
                    if (this.ndviMapInstance) {
                        if (this.ndviMouseMoveHandler) this.ndviMapInstance.off('mousemove', this.ndviMouseMoveHandler);
                        if (this.ndviMouseOutHandler) this.ndviMapInstance.off('mouseout', this.ndviMouseOutHandler);
                    }
                    this.ndviMouseMoveHandler = null;
                    this.ndviMouseOutHandler = null;
                    if (this.ndviTooltipEl) {
                        this.ndviTooltipEl.remove();
                        this.ndviTooltipEl = null;
                    }
                    if (this.ndviGeoRasterLayer) { this.ndviMapInstance && this.ndviMapInstance.removeLayer(this.ndviGeoRasterLayer); this.ndviGeoRasterLayer = null; }
                    this.ndviGeoRaster = null;
                    if (this.ndviMapInstance) { this.ndviMapInstance.remove(); this.ndviMapInstance = null; }
                },

                clearNdviMapSelection() {
                    this.ndviMapLoading = false;
                    this.ndviMapErrorMessage = '';
                    this.selectedNdviDate = '';
                    this.selectedNdviValue = null;
                    this.selectedNdviFileId = '';
                    this.destroyMap();
                },


                parseGeoJsonPayload(payload) {
                    const parsed = typeof payload === 'string' ? JSON.parse(payload.trim() || '{}') : payload;
                    if (!parsed || typeof parsed !== 'object') throw new Error(<?php echo tj('vegetation.invalid_geojson'); ?>);
                    if (parsed.type === 'FeatureCollection' && Array.isArray(parsed.features)) return parsed;
                    if (parsed.type === 'Feature') return { type: 'FeatureCollection', features: [parsed] };
                    if (Array.isArray(parsed.features)) return { type: 'FeatureCollection', features: parsed.features };
                    throw new Error(<?php echo tj('vegetation.invalid_geojson'); ?>);
                },

                renderNdviGeoJson(geoJson) {
                    if (!this.ndviMapInstance) return;
                    if (this.ndviGeoJsonLayer) { this.ndviGeoJsonLayer.remove(); this.ndviGeoJsonLayer = null; }

                    this.ndviGeoJsonLayer = L.geoJSON(geoJson, {
                        style: feature => {
                            const ndvi = this.toNumberOrNull(feature?.properties?.ndvi);
                            return {
                                color: '#263238', weight: 0.3,
                                fillColor: this.resolveNdviColor(ndvi),
                                fillOpacity: 0.72
                            };
                        },
                        onEachFeature: (feature, layer) => {
                            const ndvi = this.toNumberOrNull(feature?.properties?.ndvi);
                            const label = ndvi === null ? '--' : ndvi.toFixed(3);
                            layer.bindTooltip(`NDVI: ${label}`);
                        }
                    }).addTo(this.ndviMapInstance);

                    const bounds = this.ndviGeoJsonLayer.getBounds();
                    if (bounds && bounds.isValid()) {
                        this.ndviMapInstance.fitBounds(bounds, { padding: [18, 18], maxZoom: 18 });
                    }
                },

                resolveNdviColor(ndviValue) {
                    if (ndviValue === null) return '#9e9e9e';
                    if (ndviValue >= 0.8) return '#1b5e20';
                    if (ndviValue >= 0.6) return '#43a047';
                    if (ndviValue >= 0.4) return '#8bc34a';
                    if (ndviValue >= 0.2) return '#fdd835';
                    return '#e53935';
                },

                async loadNdviGeoTiffForRow(row) {
                    const fileId = String(row.fileId || '').trim();
                    this.selectedNdviDate = String(row.date || '');
                    this.selectedNdviValue = row.ndvi;

                    if (fileId === '') {
                        this.selectedNdviFileId = '';
                        this.ndviMapErrorMessage = <?php echo tj('vegetation.no_ndvi_file'); ?>;
                        return;
                    }

                    // initializeMap() is a no-op if the map already exists, so this flag
                    // tells us whether this is the very first raster loaded into this map
                    // instance (in which case we should fit the view to it) or a subsequent
                    // datapoint switch (in which case the user's current pan/zoom should be
                    // preserved instead of being reset).
                    const isFirstLoad = !this.ndviMapInstance;
                    this.initializeMap();
                    this.selectedNdviFileId = fileId;
                    this.ndviMapErrorMessage = '';
                    this.ndviMapLoading = true;

                    try {
                        const url = `${ecoApiUrl}files.php?krd=${encodeURIComponent(krd)}&tif=${encodeURIComponent(fileId)}`;
                        console.log('[Vegetation] Fetching GeoTIFF:', url);

                        const geoTiffToken = this.getAuthToken();
                        const response = await fetch(url, {
                            signal: AbortSignal.timeout(30000),
                            headers: geoTiffToken ? { Authorization: 'Bearer ' + geoTiffToken } : {}
                        });
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        
                        const blob = await response.blob();
                        const arrayBuffer = await blob.arrayBuffer();
                        
                        // Clean up previous layer
                        if (this.ndviGeoRasterLayer) {
                            this.ndviMapInstance.removeLayer(this.ndviGeoRasterLayer);
                            this.ndviGeoRasterLayer = null;
                        }
                        this.ndviGeoRaster = null;

                        // Decode GeoTIFF (GeoRaster() returns a Promise in UMD builds).
                        // GeoRaster() is the parseGeoraster() function: it expects the raw
                        // data (ArrayBuffer/Blob/Buffer/url) as its first argument, not an
                        // options object, otherwise it can't detect sourceType/rasterType.
                        const libs = window.__vegLibs;
                        if (!libs || !libs.GeoRaster) throw new Error('GeoRaster library not available');

                        this.ndviGeoRaster = await libs.GeoRaster(arrayBuffer);

                        if (!this.ndviGeoRaster) {
                            throw new Error('Falha ao decodificar GeoTIFF');
                        }

                        console.log('[Vegetation] GeoTIFF decoded:', JSON.stringify({
                            width: this.ndviGeoRaster.width,
                            height: this.ndviGeoRaster.height,
                            rasters: this.ndviGeoRaster.rasters?.length,
                            xmin: this.ndviGeoRaster.xmin,
                            ymin: this.ndviGeoRaster.ymin,
                            xmax: this.ndviGeoRaster.xmax,
                            ymax: this.ndviGeoRaster.ymax,
                            projection: this.ndviGeoRaster.projection,
                            sourceType: this.ndviGeoRaster.sourceType
                        }));
                        
                        // Default projection to the numeric EPSG code 4326 only when missing.
                        // GeoRasterLayer's supported-projection check expects the raw numeric
                        // EPSG code (e.g. 4326), not a string like 'EPSG:4326', so we must not
                        // overwrite a valid numeric projection already parsed from the GeoTIFF.
                        if (!this.ndviGeoRaster.projection) {
                            console.log('[Vegetation] Setting default projection EPSG:4326');
                            this.ndviGeoRaster.projection = 4326;
                        } else {
                            console.log('[Vegetation] GeoTIFF projection:', this.ndviGeoRaster.projection);
                        }

                        // This raster is a single small image (171x204px), not a tiled dataset.
                        // GeoRasterLayer renders rasters as Leaflet tiles, which caused several
                        // issues here: tiles not drawn until a zoomend-triggered redraw, and
                        // visible seams/"split" artifacts at tile boundaries. Since we only ever
                        // need to display one small raster at a time, it's simpler and more
                        // reliable to rasterize it once onto an offscreen canvas ourselves and
                        // show it as a single L.imageOverlay (no tiling involved at all).
                        const resolveColor = this.resolveNdviColor;
                        const raster = this.ndviGeoRaster;
                        const rWidth = raster.width;
                        const rHeight = raster.height;
                        const band0 = raster.values[0];
                        const band1 = raster.values.length > 1 ? raster.values[1] : null;

                        const canvas = document.createElement('canvas');
                        canvas.width = rWidth;
                        canvas.height = rHeight;
                        const ctx = canvas.getContext('2d');
                        const imageData = ctx.createImageData(rWidth, rHeight);
                        for (let row = 0; row < rHeight; row++) {
                            const rowBand0 = band0[row];
                            const rowBand1 = band1 ? band1[row] : null;
                            for (let col = 0; col < rWidth; col++) {
                                const idx = (row * rWidth + col) * 4;
                                const raw = rowBand0[col];
                                const mask = rowBand1 ? rowBand1[col] : 255;
                                if (raw === null || raw === undefined || raw === 0 || mask === 0) {
                                    imageData.data[idx + 3] = 0; // fully transparent (nodata/masked)
                                    continue;
                                }
                                const ndviValue = raw / 255;
                                const hex = resolveColor(ndviValue);
                                const r = parseInt(hex.slice(1, 3), 16);
                                const g = parseInt(hex.slice(3, 5), 16);
                                const b = parseInt(hex.slice(5, 7), 16);
                                imageData.data[idx] = r;
                                imageData.data[idx + 1] = g;
                                imageData.data[idx + 2] = b;
                                imageData.data[idx + 3] = 255;
                            }
                        }
                        ctx.putImageData(imageData, 0, 0);

                        console.log('[Vegetation] Rasterized NDVI canvas:', { width: rWidth, height: rHeight });

                        // Fit bounds to the raster extent
                        const { ymin: south, xmin: west, ymax: north, xmax: east } = raster;
                        const bounds = L.latLngBounds([south, west], [north, east]);

                        this.ndviGeoRasterLayer = L.imageOverlay(canvas.toDataURL(), bounds, {
                            opacity: 0.85,
                            zIndex: 1000,
                            interactive: true,
                        });
                        this.ndviGeoRasterLayer.addTo(this.ndviMapInstance);

                        // Only fit the map view to the raster the first time it's shown for
                        // this map instance. Subsequent datapoint switches keep whatever
                        // pan/zoom the user has set instead of resetting the view every time.
                        if (isFirstLoad && bounds.isValid()) {
                            this.ndviMapInstance.fitBounds(bounds, { padding: [18, 18], maxZoom: 18 });
                        }

                        // Show pixel value on hover using a plain custom tooltip element
                        // (see initializeMap() for why we avoid Leaflet's bindTooltip here).
                        const pixelWidth = (raster.xmax - raster.xmin) / raster.width;
                        const pixelHeight = (raster.ymax - raster.ymin) / raster.height;
                        // Cursor must be set on the raster <img> element itself, not the map
                        // container: interactive:true adds Leaflet's .leaflet-interactive class
                        // to the image, which sets `cursor: pointer` directly on that element.
                        // An element's own inline/class cursor style always wins over an
                        // ancestor's, so clearing the container's cursor has no visible effect
                        // while the mouse is over the image.
                        const imgEl = this.ndviGeoRasterLayer.getElement();
                        if (this.ndviMouseMoveHandler) {
                            this.ndviMapInstance.off('mousemove', this.ndviMouseMoveHandler);
                        }
                        this.ndviMouseMoveHandler = (e) => {
                            if (!this.ndviGeoRaster || !this.ndviGeoRasterLayer || !this.ndviTooltipEl) return;
                            const { lat, lng } = e.latlng;
                            const col = Math.floor((lng - raster.xmin) / pixelWidth);
                            const row = Math.floor((raster.ymax - lat) / pixelHeight);
                            if (row < 0 || row >= raster.height || col < 0 || col >= raster.width) {
                                this.ndviTooltipEl.style.display = 'none';
                                // Outside the raster: force the standard map-drag hand cursor
                                // (can't just clear it here, since .leaflet-interactive would
                                // immediately reassert `pointer` via CSS otherwise).
                                if (imgEl) imgEl.style.cursor = 'grab';
                                return;
                            }
                            const raw = band0[row][col];
                            const mask = band1 ? band1[row][col] : 255;
                            if (raw === null || raw === undefined || raw === 0 || mask === 0) {
                                this.ndviTooltipEl.style.display = 'none';
                                // Transparent/nodata pixel: same as above, map should still
                                // feel draggable here even though it's within the image bounds.
                                if (imgEl) imgEl.style.cursor = 'grab';
                                return;
                            }
                            const label = (raw / 255).toFixed(3);
                            this.ndviTooltipEl.textContent = `NDVI: ${label}`;
                            this.ndviTooltipEl.style.display = 'block';
                            this.ndviTooltipEl.style.left = `${e.containerPoint.x + 12}px`;
                            this.ndviTooltipEl.style.top = `${e.containerPoint.y - 12}px`;
                            if (imgEl) imgEl.style.cursor = 'pointer';
                        };
                        this.ndviMapInstance.on('mousemove', this.ndviMouseMoveHandler);
                        if (this.ndviMouseOutHandler) {
                            this.ndviMapInstance.off('mouseout', this.ndviMouseOutHandler);
                        }
                        this.ndviMouseOutHandler = () => {
                            if (this.ndviTooltipEl) this.ndviTooltipEl.style.display = 'none';
                            if (imgEl) imgEl.style.cursor = 'grab';
                        };
                        this.ndviMapInstance.on('mouseout', this.ndviMouseOutHandler);

                        console.log('[Vegetation] NDVI image overlay added successfully');

                    } catch (error) {
                        this.selectedNdviFileId = '';
                        this.ndviGeoRaster = null;
                        this.ndviMapErrorMessage = <?php echo tj('vegetation.map_failed'); ?> + error.message;
                        console.error('[Vegetation] Error loading NDVI map:', error);
                    } finally {
                        this.ndviMapLoading = false;
                    }
                },

                async loadLatestAvailableNdviMap() {
                    for (let i = this.rows.length - 1; i >= 0; i--) {
                        const row = this.rows[i];
                        if (!row || row.ndvi === null) continue;
                        const fileId = String(row.fileId || '').trim();
                        if (fileId === '') continue;
                        if (this.selectedNdviFileId === fileId && this.selectedNdviDate === row.date && this.ndviGeoRasterLayer) return;
                        await this.loadNdviGeoTiffForRow(row);
                        return;
                    }
                    this.clearNdviMapSelection();
                },

                computeDateRange() {
                    const today = new Date();
                    const oneYearAgo = new Date(today);
                    oneYearAgo.setFullYear(today.getFullYear() - 1);
                    return {
                        ini: this.formatDate(oneYearAgo),
                        fim: this.formatDate(today)
                    };
                },

                formatDate(date) {
                    const y = date.getFullYear();
                    const m = String(date.getMonth() + 1).padStart(2, '0');
                    const d = String(date.getDate()).padStart(2, '0');
                    return `${y}-${m}-${d}`;
                }
            }
        });

        // Wait for Vue app to be ready before calling methods
        setTimeout(() => {
            app.use(Quasar);
            app.mount('#q-app');
        }, 50);

        window.vegetationApi = {
            getRows: () => app._data.rows,
            getKrd: () => krd,
            getAoi: () => aoi
        };
    })();
    </script>
</body>
</html>
