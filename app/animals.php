<?php
// app/animals.php - Animal group catalogue with Quasar UMD (Vue 3 + Quasar 2)
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('animals.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        .animals-table {
            height: calc(100vh - 180px);
        }

        .animals-table thead th {
            text-align: left;
            white-space: normal;
        }

        @media (max-width: 599px) {
            .animals-table {
                height: calc(100vh - 160px);
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
                        <q-icon name="pets" size="md" class="q-mr-sm"></q-icon>
                        <?php echo th('animals.toolbar'); ?>
                    </q-toolbar-title>
                    <q-space></q-space>
                </q-toolbar>
            </q-header>

            <q-page-container>
                <q-page class="q-pa-md">

                    <q-linear-progress v-if="loading" color="primary" class="q-mb-md" indeterminate></q-linear-progress>

                    <q-table class="animals-table full-width" flat
                        :rows="animals" :columns="columns" row-key="id"
                        :filter="filter" :loading="loading" separator="cell" virtual-scroll
                        hide-pagination :rows-per-page-options="[0]"
                        style="height: calc(100vh - 100px);"
                        @row-click="handleRowClick"
                        :no-data-label="<?php echo tv('animals.no_data'); ?>"
                        :no-results-label="<?php echo tv('animals.no_results'); ?>">
                        <template v-slot:top-left>
                            <div class="row items-center q-gutter-sm no-wrap">
                                <q-btn color="primary" icon="add" label="<?php echo th('animals.add'); ?>" @click="openForm" style="width: 250px;"></q-btn>
                            </div>
                        </template>
                        <template v-slot:top-right>
                            <div class="row items-center q-gutter-sm no-wrap">
                                <q-input outlined dense debounce="300" v-model="filter" placeholder="<?php echo th('common.search'); ?>" style="width: 50%;">
                                    <template v-slot:append>
                                        <q-btn v-if="filter" flat round dense icon="close" @click.stop="filter = ''"></q-btn>
                                        <q-icon name="search"></q-icon>
                                    </template>
                                </q-input>
                                <q-btn flat round dense icon="refresh" @click="fetchAnimals" :loading="loading"></q-btn>
                            </div>
                        </template>
                        <template v-slot:body-cell-name="props">
                            <q-td :props="props" class="text-weight-medium">{{ props.row.name }}</q-td>
                        </template>
                        <template v-slot:body-cell-description="props">
                            <q-td :props="props">{{ props.row.description }}</q-td>
                        </template>
                        <template v-slot:body-cell-created_at="props">
                            <q-td :props="props" class="text-grey-8">{{ formatDate(props.row.created_at) }}</q-td>
                        </template>
                    </q-table>
                </q-page>
            </q-page-container>
        </q-layout>

        <q-dialog v-model="showForm" persistent maximized>
            <q-card class="form-card" style="width: 100%; max-width: 100vw;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6">{{ isEditing ? <?php echo tj('animals.edit_title'); ?> : <?php echo tj('animals.add_title'); ?> }}</div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense @click="closeForm"></q-btn>
                </q-card-section>
                <q-separator></q-separator>

                <q-card-section class="q-pt-md form-scroll">
                    <form @submit.prevent="handleSubmit">
                        <div class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500"><?php echo th('animals.name'); ?></label>
                            <q-input v-model="form.name" outlined dense maxlength="20" counter
                                placeholder="<?php echo th('animals.name_placeholder'); ?>"
                                hint="<?php echo th('animals.name_hint'); ?>"
                                :error="!!errors.name" :error-message="errors.name"
                                :rules="[val => !!(val && val.trim()) || <?php echo tv('common.required_field'); ?>]">
                            </q-input>
                        </div>

                        <div class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500"><?php echo th('animals.description'); ?></label>
                            <q-input v-model="form.description" type="textarea" outlined maxlength="255" counter
                                placeholder="<?php echo th('animals.description_placeholder'); ?>"
                                input-style="min-height: 120px;">
                            </q-input>
                        </div>
                    </form>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-actions align="right">
                    <q-btn v-if="isEditing" icon="delete" label="<?php echo th('common.delete'); ?>" @click="handleDelete"></q-btn>
                    <q-space></q-space>
                    <q-btn label="<?php echo th('common.cancel'); ?>" @click="closeForm"></q-btn>
                    <q-btn label="<?php echo th('common.save'); ?>" color="primary" @click="handleSubmit" :loading="saving"></q-btn>
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>

    <?php require_once __DIR__ . '/auth-guard.php'; ?>

    <script>
        const app = Vue.createApp({
            data() {
                return {
                    animals: [],
                    loading: false,
                    filter: '',

                    krdValue: '',
                    authToken: '',

                    showForm: false,
                    saving: false,
                    form: {
                        id: null,
                        name: '',
                        description: ''
                    },
                    errors: {
                        name: null
                    },

                    API_URL: '<?php echo OPS_API_URL; ?>'
                };
            },
            computed: {
                isEditing() {
                    return !!this.form.id;
                },
                columns() {
                    return [{
                            name: 'name',
                            label: <?php echo tj('animals.col_name'); ?>,
                            field: 'name',
                            align: 'left',
                            sortable: true,
                            style: 'width: 180px;'
                        },
                        {
                            name: 'description',
                            label: <?php echo tj('animals.col_description'); ?>,
                            field: 'description',
                            align: 'left',
                            sortable: true
                        },
                        {
                            name: 'created_at',
                            label: <?php echo tj('animals.col_created'); ?>,
                            field: 'created_at',
                            align: 'left',
                            sortable: true,
                            style: 'width: 160px;'
                        }
                    ];
                }
            },
            methods: {
                showNotification(message, color, icon) {
                    this.$q.notify({
                        message: message,
                        color: color === 'positive' ? 'positive' : (color === 'warning' ? 'warning' : 'negative'),
                        icon: icon || 'error',
                        position: 'top',
                        timeout: 4000,
                        html: true
                    });
                },

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    return d.toLocaleDateString('<?php echo lang_locale(); ?>', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    });
                },

                getRequestHeaders() {
                    const headers = {
                        'Accept': 'application/json'
                    };
                    if (this.authToken) {
                        headers['Authorization'] = 'Bearer ' + this.authToken;
                    }
                    return headers;
                },

                buildApiUrl(endpoint, params = {}) {
                    const url = new URL(`${this.API_URL}${endpoint}`);
                    if (this.krdValue) {
                        params.krd = this.krdValue;
                    }
                    Object.keys(params).forEach(key => {
                        url.searchParams.append(key, params[key]);
                    });
                    return url.toString();
                },

                async fetchAnimals() {
                    this.loading = true;
                    try {
                        const url = this.buildApiUrl('/animals.php?action=read');
                        const response = await fetch(url, {
                            headers: this.getRequestHeaders()
                        });
                        const data = await response.json();
                        this.animals = data.success ? (data.data || []) : [];
                        if (!data.success) {
                            this.showNotification(data.message || <?php echo tj('animals.load_failed'); ?>, 'negative', 'error');
                        }
                    } catch (error) {
                        console.error('Error loading animals:', error);
                        this.animals = [];
                        this.showNotification(<?php echo tj('animals.load_failed'); ?>, 'negative', 'cloud_off');
                    } finally {
                        this.loading = false;
                    }
                },

                openForm(item = null) {
                    this.form = {
                        id: item?.id || null,
                        name: item?.name || '',
                        description: item?.description || ''
                    };
                    this.errors = {
                        name: null
                    };
                    this.showForm = true;
                },

                closeForm() {
                    this.showForm = false;
                },

                handleDelete() {
                    if (!this.form.id) return;
                    this.$q.dialog({
                            title: <?php echo tj('common.confirm_delete'); ?>,
                            message: <?php echo tj('animals.delete_confirm'); ?>,
                            cancel: true,
                            persistent: true
                        })
                        .onOk(() => this.performDelete());
                },

                async performDelete() {
                    try {
                        const url = this.buildApiUrl('/animals.php?action=delete');
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                ...this.getRequestHeaders(),
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                id: this.form.id
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.showNotification(<?php echo tj('animals.deleted'); ?>, 'positive', 'check');
                            this.closeForm();
                            this.fetchAnimals();
                        } else {
                            this.showNotification(data.message || <?php echo tj('animals.delete_failed'); ?>, 'negative', 'error');
                        }
                    } catch (e) {
                        this.showNotification(<?php echo tj('common.network_error'); ?>, 'negative', 'cloud_off');
                    }
                },

                async handleSubmit() {
                    this.errors = {
                        name: null
                    };
                    if (!this.form.name || !this.form.name.trim()) {
                        this.errors.name = <?php echo tj('common.required_field'); ?>;
                        return;
                    }

                    this.saving = true;
                    try {
                        const action = this.form.id ? 'update' : 'create';
                        const payload = {
                            name: this.form.name.trim(),
                            description: this.form.description ? this.form.description.trim() : null
                        };
                        if (this.form.id) {
                            payload.id = this.form.id;
                        }

                        const url = this.buildApiUrl('/animals.php?action=' + action);
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                ...this.getRequestHeaders(),
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.showNotification(action === 'create' ? <?php echo tj('animals.created'); ?> : <?php echo tj('animals.updated'); ?>, 'positive', 'check');
                            this.closeForm();
                            this.fetchAnimals();
                        } else {
                            this.showNotification(data.message || <?php echo tj('animals.save_failed'); ?>, 'negative', 'error');
                        }
                    } catch (e) {
                        this.showNotification(<?php echo tj('common.network_error'); ?>, 'negative', 'cloud_off');
                    } finally {
                        this.saving = false;
                    }
                },

                handleRowClick(event, row) {
                    this.openForm(row);
                }
            },
            mounted() {
                const params = new URLSearchParams(window.location.search);
                const krd = params.get('krd') || window.parent.krd;
                if (krd) {
                    this.krdValue = krd;
                } else {
                    this.showNotification(<?php echo tj('common.krd_missing'); ?>, 'warning', 'warning');
                }

                this.authToken = localStorage.getItem('auth_token') || '';

                this.fetchAnimals();
            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>

</html>
