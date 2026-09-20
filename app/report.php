<?php
// app/report.php - read-only report trees, vegetal and livestock.
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('report.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        .report-panels {
            height: calc(100vh - 210px);
        }

        @media (max-width: 599px) {
            .report-panels {
                height: calc(100vh - 190px);
            }
        }
    </style>
</head>

<body>
    <div id="q-app">
        <q-layout view="hHh lpR fFf">
            <q-header class="ag-base-q-header">
                <q-toolbar>
                    <q-space></q-space>
                    <q-toolbar-title shrink>
                        <q-icon name="account_tree" size="md" class="q-mr-sm"></q-icon>
                        <?php echo th('report.toolbar'); ?>
                    </q-toolbar-title>
                    <q-space></q-space>
                </q-toolbar>
            </q-header>

            <q-page-container>
                <q-page class="q-pa-md">
                    <q-linear-progress v-if="loading" color="primary" class="q-mb-md" indeterminate></q-linear-progress>

                    <div class="row items-center q-gutter-sm no-wrap q-py-sm">
                        <q-input outlined dense debounce="300" v-model="filter" placeholder="<?php echo th('common.search'); ?>" style="width: 50%;">
                            <template v-slot:append>
                                <q-btn v-if="filter" flat round dense icon="close" @click.stop="filter = ''"></q-btn>
                                <q-icon name="search"></q-icon>
                            </template>
                        </q-input>
                        <q-space></q-space>
                        <q-btn flat round dense icon="unfold_more" @click="expandAll"></q-btn>
                        <q-btn flat round dense icon="unfold_less" @click="collapseAll"></q-btn>
                        <q-btn flat round dense icon="download" @click="exportXlsx" :disable="loading"></q-btn>
                        <q-btn flat round dense icon="refresh" @click="loadReport" :loading="loading"></q-btn>
                    </div>

                    <q-tabs
                        v-model="tab"
                        class="bg-white text-primary"
                        dense
                        indicator-color="primary"
                        align="left">
                        <q-tab name="vegetal" icon="grass" label="<?php echo th('report.tab_vegetal'); ?>"></q-tab>
                        <q-tab name="livestock" icon="pets" label="<?php echo th('report.tab_livestock'); ?>"></q-tab>
                    </q-tabs>
                    <q-separator></q-separator>

                    <q-tab-panels class="report-panels" v-model="tab">
                        <q-tab-panel name="vegetal" class="q-pa-sm">
                            <q-tree
                                v-if="vegetal.length > 0"
                                ref="vegetalTree"
                                :nodes="vegetal"
                                node-key="id"
                                label-key="label"
                                :filter="filter"
                                :filter-method="filterNode"
                                squared
                                dense>
                                <template v-slot:default-header="props">
                                    <q-item class="full-width q-pa-none" :style="rowIndent(props.node)">
                                        <q-item-section avatar>
                                            <q-icon :name="levelIcon(props.node.level)" color="primary"></q-icon>
                                        </q-item-section>
                                        <q-item-section>
                                            <q-item-label>{{ props.node.label }}</q-item-label>
                                            <q-item-label caption>{{ props.node.caption }}</q-item-label>
                                        </q-item-section>
                                    </q-item>
                                </template>
                            </q-tree>
                            <div v-else-if="!loading" class="text-center q-pa-md text-grey-7">
                                <?php echo th('report.empty'); ?>
                            </div>
                        </q-tab-panel>

                        <q-tab-panel name="livestock" class="q-pa-sm">
                            <q-tree
                                v-if="livestock.length > 0"
                                ref="livestockTree"
                                :nodes="livestock"
                                node-key="id"
                                label-key="label"
                                :filter="filter"
                                :filter-method="filterNode"
                                squared
                                dense>
                                <template v-slot:default-header="props">
                                    <q-item class="full-width q-pa-none" :style="rowIndent(props.node)">
                                        <q-item-section avatar>
                                            <q-icon :name="levelIcon(props.node.level)" color="primary"></q-icon>
                                        </q-item-section>
                                        <q-item-section>
                                            <q-item-label>{{ props.node.label }}</q-item-label>
                                            <q-item-label caption>{{ props.node.caption }}</q-item-label>
                                        </q-item-section>
                                    </q-item>
                                </template>
                            </q-tree>
                            <div v-else-if="!loading" class="text-center q-pa-md text-grey-7">
                                <?php echo th('report.empty'); ?>
                            </div>
                        </q-tab-panel>
                    </q-tab-panels>
                </q-page>
            </q-page-container>
        </q-layout>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>

    <script>
        const app = Vue.createApp({
            data() {
                return {
                    loading: true,
                    tab: 'vegetal',
                    vegetal: [],
                    livestock: [],
                    filter: '',
                    krdValue: ''
                };
            },

            methods: {
                showNotification(message, color, icon) {
                    this.$q.notify({ message, color, icon: icon || 'error', position: 'top', timeout: 4000 });
                },

                // Captions carry the dates, rates and areas, so matching the
                // label alone would miss most of what the tree shows.
                filterNode(node, filter) {
                    const needle = filter.toLowerCase();
                    return (node.label || '').toLowerCase().includes(needle)
                        || (node.caption || '').toLowerCase().includes(needle);
                },

                // Whichever tab is showing; the other is a different QTree.
                activeTree() {
                    return this.$refs[this.tab + 'Tree'];
                },

                expandAll() {
                    const tree = this.activeTree();
                    if (tree) tree.expandAll();
                },

                collapseAll() {
                    const tree = this.activeTree();
                    if (tree) tree.collapseAll();
                },

                // No children means no expand arrow, so the row starts 25px
                // (the arrow box) left of its siblings that have one.
                rowIndent(node) {
                    return node.children && node.children.length
                        ? null
                        : 'padding-left: 8px';
                },

                levelIcon(level) {
                    return {
                        field: 'pin_drop',
                        crop: 'grass',
                        animal: 'pets',
                        operation: 'local_florist',
                        input: 'science'
                    }[level] || 'circle';
                },


                // Fetched rather than linked: the endpoint needs the bearer token,
                // which a plain href cannot carry.
                async exportXlsx() {
                    try {
                        const token = localStorage.getItem('auth_token');
                        const headers = {};
                        if (token) headers['Authorization'] = 'Bearer ' + token;

                        const url = '<?php echo ECO_API_URL; ?>report.php?export=xlsx&lang=<?php echo lang_code(); ?>&krd='
                            + encodeURIComponent(this.krdValue);
                        const response = await fetch(url, { headers });
                        if (!response.ok) throw new Error('HTTP ' + response.status);

                        const disposition = response.headers.get('Content-Disposition') || '';
                        const match = disposition.match(/filename="([^"]+)"/);

                        const blob = await response.blob();
                        const href = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = href;
                        link.download = match ? match[1] : 'report.xlsx';
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        URL.revokeObjectURL(href);
                    } catch (error) {
                        console.error('Export report error:', error);
                        this.showNotification(<?php echo tj('report.export_failed'); ?> + error.message, 'negative', 'error');
                    }
                },

                async loadReport() {
                    this.loading = true;
                    try {
                        const token = localStorage.getItem('auth_token');
                        const headers = { 'Accept': 'application/json' };
                        if (token) headers['Authorization'] = 'Bearer ' + token;

                        const url = '<?php echo ECO_API_URL; ?>report.php?krd=' + encodeURIComponent(this.krdValue);
                        const response = await fetch(url, { headers });
                        const body = await response.json();
                        if (!response.ok || body.success !== true) {
                            throw new Error(body.message || ('HTTP ' + response.status));
                        }

                        this.vegetal = body.data.vegetal || [];
                        this.livestock = body.data.livestock || [];
                    } catch (error) {
                        console.error('Load report error:', error);
                        this.showNotification(<?php echo tj('report.load_failed'); ?> + error.message, 'negative', 'cloud_off');
                    } finally {
                        this.loading = false;
                    }
                }
            },

            mounted() {
                const urlParams = new URLSearchParams(window.location.search);
                this.krdValue = urlParams.get('krd') || window.parent.krd || '';

                if (this.krdValue) {
                    this.loadReport();
                } else {
                    this.showNotification(<?php echo tj('common.krd_missing'); ?>, 'warning', 'warning');
                    this.loading = false;
                }
            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>

</html>
