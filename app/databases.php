<?php
// goa/app/databases.php - Databases listing page using Quasar Framework UMD
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('databases.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        .db-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .db-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .db-name {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .empty-state {
            min-height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body>
    <div id="q-app">
        <q-layout view="hHh lpR fFf">
            <!-- Header / Navbar -->
            <q-header class="ag-base-q-header">
                <q-toolbar>
                    <q-toolbar-title shrink>
                        <q-icon name="storage" size="md" class="q-mr-sm"></q-icon>
                        <?php echo th('databases.toolbar'); ?>
                    </q-toolbar-title>

                    <q-space></q-space>

                    <q-btn
                        dense
                        icon="add_business"
                        label="<?php echo th('databases.subscriptions'); ?>"
                        @click="openSubscriptions"></q-btn>
                </q-toolbar>
            </q-header>

            <!-- Page Content -->
            <q-page-container>
                <q-page class="q-pa-md">
                    <!-- Linear Progress during loading -->
                    <q-linear-progress
                        v-if="loading"
                        color="primary"
                        class="q-mb-md"
                        indeterminate></q-linear-progress>

                    <!-- Loading Skeleton Cards -->
                    <div v-if="loading" class="row q-col-gutter-md">
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3" v-for="n in 6" :key="n">
                            <q-card class="full-height rounded-borders">
                                <q-card-section class="bg-grey-2">
                                    <q-skeleton type="text" class="text-subtitle1"></q-skeleton>
                                </q-card-section>
                                <q-card-section>
                                    <q-skeleton type="text" width="90%"></q-skeleton>
                                    <q-skeleton type="text" width="75%" class="q-mt-sm"></q-skeleton>
                                    <q-skeleton type="text" width="85%" class="q-mt-sm"></q-skeleton>
                                </q-card-section>
                                <q-card-actions align="center">
                                    <q-skeleton type="button" size="md"></q-skeleton>
                                </q-card-actions>
                            </q-card>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="!loading && databases.length === 0" class="empty-state">
                        <div class="text-center">
                            <q-icon name="info" size="80px" color="grey-5"></q-icon>
                            <div class="text-h6 text-grey-7 q-mt-md"><?php echo th('databases.empty_title'); ?></div>
                            <div class="text-body2 text-grey-6 q-mt-sm"><?php echo th('databases.empty_body'); ?></div>
                            <q-btn
                                icon="refresh"
                                label="<?php echo th('common.try_again'); ?>"
                                class="q-mt-lg"
                                @click="fetchDatabases"></q-btn>
                            <q-btn
                                color="primary"
                                icon="add_business"
                                label="<?php echo th('databases.create_db'); ?>"
                                class="q-mt-lg q-ml-sm"
                                @click="openSubscriptions"></q-btn>
                        </div>
                    </div>

                    <!-- Database Cards Grid -->
                    <div v-else class="row q-col-gutter-md">
                        <div
                            class="col-12 col-sm-6 col-md-4 col-lg-3"
                            v-for="db in databases"
                            :key="db.krd">
                            <q-card class="db-card full-height rounded-borders">
                                <q-card-section class="row items-center no-wrap q-py-sm">
                                    <q-avatar class="q-mr-sm" color="primary" text-color="white" size="md">
                                        <q-icon name="storage" size="xs"></q-icon>
                                    </q-avatar>
                                    <div class="col" style="min-width: 0;">
                                        <div class="text-primary db-name ellipsis">{{ db.name }}</div>
                                        <div class="text-caption text-grey-7 ellipsis">{{ db.description && db.description !== '-' ? db.description : <?php echo tj('databases.no_description'); ?> }}</div>
                                    </div>
                                </q-card-section>
                                <q-card-actions align="center" class="q-pt-none">
                                    <q-btn
                                        color="primary"
                                        icon="open_in_new"
                                        label="<?php echo th('common.connect'); ?>"
                                        class="full-width"
                                        dense
                                        @click="handleConnect(db)"></q-btn>
                                </q-card-actions>
                            </q-card>
                        </div>
                    </div>
                </q-page>
            </q-page-container>
        </q-layout>

    </div>

    <!-- Vue.js -->
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <!-- Quasar Framework JS -->
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>

    <script>
        // Initialize Vue + Quasar app
        const app = Vue.createApp({
            data() {
                return {
                    databases: [],
                    loading: false,


                    // API endpoint
                    databasesUrl: '<?php echo ECO_API_URL; ?>databases.php'
                };
            },

            methods: {
                // Show notification
                showNotification(message, color, icon) {
                    this.$q.notify({
                        message: message,
                        color: color === 'positive' ? 'positive' : (color === 'warning' ? 'warning' : (color === 'info' ? 'info' : 'negative')),
                        icon: icon || 'error',
                        position: 'top',
                        timeout: 4000,
                        html: true
                    });
                },

                // Check authentication on mount
                checkAuth() {
                    const token = localStorage.getItem('auth_token');
                    if (!token) {
                        this.showNotification(<?php echo tj('common.session_expired'); ?>, 'warning', 'warning');
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 1500);
                        return false;
                    }
                    return true;
                },

                // Fetch databases from API
                async fetchDatabases() {
                    if (!this.checkAuth()) return;

                    this.loading = true;

                    try {
                        const token = localStorage.getItem('auth_token');
                        
                        const headers = {
                            'Accept': 'application/json'
                        };
                        
                        if (token) {
                            headers['Authorization'] = 'Bearer ' + token;
                        }
                        
                        var response = await fetch(this.databasesUrl, {
                            method: 'GET',
                            headers: headers
                        });


                        if (response.status === 401 || response.status === 403) {
                            this.showNotification(<?php echo tj('common.session_expired'); ?>, 'warning', 'warning');
                            setTimeout(() => {
                                window.location.href = 'login.php';
                            }, 1500);
                            return;
                        }

                        if (!response.ok) {
                            throw new Error(<?php echo tj('databases.server_error'); ?> + response.status);
                        }

                        const data = await response.json();
                        this.databases = data || [];

                    } catch (error) {
                        console.error('Fetch databases error:', error);
                        this.showNotification(<?php echo tj('databases.load_failed'); ?>, 'negative', 'cloud_off');
                    } finally {
                        this.loading = false;
                    }
                },

                // Handle database connection
                handleConnect(db) {
                    // Set krd/dbName on parent window and navigate to fields.php
                    window.parent.krd = db.krd;
                    window.parent.dbName = db.name;
                    if (typeof window.parent.refreshMenuAuthState === 'function') {
                        window.parent.refreshMenuAuthState();
                    }
                    window.location.href = 'fields.php';
                },

                openSubscriptions() {
                    window.location.href = 'subscribe.php';
                }
            },

            // Fetch databases when component mounts
            mounted() {
                this.fetchDatabases();
            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>

</html>