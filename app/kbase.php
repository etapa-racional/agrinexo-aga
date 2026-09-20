<?php
// app/kbase.php - Knowledge Base management page (Quasar UMD, Vue 3)
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('kbase.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        .kbase-table {
            height: calc(100vh - 180px);
        }

        .dialog-full-height .q-dialog__bg {
            max-height: 95vh;
        }

        @media (max-width: 599px) {
            .kbase-table {
                height: calc(100vh - 160px);
            }
        }

        .tag-badge {
            margin-right: 4px;
            margin-bottom: 2px;
        }
    </style>
</head>

<body>
    <div id="q-app">
        <q-layout view="hHh lpR fFf">
            <!-- Header -->
            <q-header class="ag-base-q-header">
                <q-toolbar>
                    <q-space></q-space>
                    <q-toolbar-title shrink>
                        <q-icon name="menu_book" size="md" class="q-mr-sm"></q-icon>
                        Base de Conhecimento
                    </q-toolbar-title>
                    <q-space></q-space>
                </q-toolbar>
            </q-header>

            <!-- Page Content -->
            <q-page-container>
                <q-page class="q-pa-md">

                    <!-- Linear Progress -->
                    <q-linear-progress v-if="loading" color="primary" class="q-mb-md" indeterminate></q-linear-progress>

                    <!-- q-table -->
                    <q-table class="kbase-table full-width" flat
                        :rows="entries" :columns="columns" row-key="xxx"
                        :filter="filter" :loading="loading" separator="cell" virtual-scroll
                        hide-pagination :rows-per-page-options="[0]"
                        style="height: calc(100vh - 100px);"
                        @row-click="handleRowClick"
                        :no-data-label="<?php echo tv('kbase.no_data'); ?>"
                        :no-results-label="<?php echo tv('kbase.no_results'); ?>">
                        <template v-slot:top-left>
                            <div class="row items-center q-gutter-sm no-wrap">
                                <q-btn color="primary" icon="add" label="<?php echo th('kbase.add_entry'); ?>" @click="openForm" style="width: 250px;"></q-btn>
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
                                <q-btn flat round dense icon="refresh" @click="fetchEntries" :loading="loading"></q-btn>
                            </div>
                        </template>
                        <template v-slot:body-cell-title="props">
                            <q-td :props="props" class="text-weight-medium">{{ props.row.title }}</q-td>
                        </template>
                        <template v-slot:body-cell-tags="props">
                            <q-td :props="props">
                                <span v-for="tag in parseTags(props.row.tags)" :key="tag" class="tag-badge">
                                    <q-badge :color="getTagColor(tag)">{{ tag }}</q-badge>
                                </span>
                            </q-td>
                        </template>
                        <template v-slot:body-cell-active="props">
                            <q-td :props="props">
                                <q-badge :color="props.row.active ? 'positive' : 'negative'">
                                    {{ props.row.active ? 'Ativo' : 'Inativo' }}
                                </q-badge>
                            </q-td>
                        </template>
                        <template v-slot:body-cell-created_at="props">
                            <q-td :props="props" class="text-grey-8">{{ formatDate(props.row.created_at) }}</q-td>
                        </template>
                    </q-table>
                </q-page>
            </q-page-container>
        </q-layout>

        <!-- Edit/Add Form Dialog -->
        <q-dialog v-model="showForm" persistent maximized>
            <q-card class="form-card" style="width: 100%; max-width: 100vw;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6">{{ selectedItem?.xxx ? 'Editar Entrada' : 'Adicionar Entrada' }}</div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense @click="closeForm"></q-btn>
                </q-card-section>
                <q-separator></q-separator>

                <q-card-section class="q-pt-md form-scroll">
                    <form @submit.prevent="handleSubmit">
                        <!-- Title -->
                        <div class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500"><?php echo th('kbase.title_label'); ?></label>
                            <q-input v-model="form.title" outlined dense placeholder="<?php echo th('kbase.title_placeholder'); ?>"
                                :error="!!errors.title" :error-message="errors.title"
                                :rules="[val => !!(val && val.trim()) || <?php echo tv('common.required_field'); ?>]">
                            </q-input>
                        </div>

                        <!-- Body / Content -->
                        <div class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500"><?php echo th('kbase.body_label'); ?></label>
                            <q-input v-model="form.body" type="textarea" outlined debounce="300"
                                placeholder="<?php echo th('kbase.body_placeholder'); ?>"
                                input-style="min-height: 200px;"
                                :error="!!errors.body" :error-message="errors.body"
                                :rules="[val => !!(val && val.trim()) || <?php echo tv('common.required_field'); ?>]">
                            </q-input>
                        </div>

                        <!-- Tags -->
                        <div class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500"><?php echo th('kbase.tags_label'); ?></label>
                            <q-input v-model="form.tagsString" outlined dense placeholder="<?php echo th('kbase.tags_placeholder'); ?>"
                                hint="<?php echo th('kbase.tags_hint'); ?>">
                            </q-input>
                            <div v-if="form.tags && form.tags.length > 0" class="q-mt-sm row q-gutter-xs">
                                <q-badge v-for="(tag, idx) in form.tags" :key="idx"
                                    :color="getTagColor(tag)" class="q-mr-xs q-mb-xs">
                                    {{ tag }}
                                    <q-btn dense round flat icon="close" size="xs" 
                                        @click="form.tags.splice(idx, 1); form.tagsString = '';">
                                    </q-btn>
                                </q-badge>
                            </div>
                            <q-btn color="primary" dense class="q-mt-sm"
                                v-if="form.tagsString && form.tagsString.trim()"
                                @click="addTagFromInput">
                                <q-icon name="add" size="sm" class="q-mr-xs"></q-icon> Adicionar Tag
                            </q-btn>
                        </div>

                        <q-select v-model="form.fmd" :options="fmdOptions" emit-value map-options outlined dense class="q-mb-md"
                            label="<?php echo th('kbase.fmd'); ?>"></q-select>

                        <!-- Active Toggle -->
                        <div class="q-mb-md row items-center">
                            <q-toggle v-model="form.active" label="<?php echo th('kbase.active'); ?>" color="positive" />
                            <span class="text-caption text-grey-7 q-ml-sm">
                                <?php echo th('kbase.inactive_note'); ?>
                            </span>
                        </div>
                    </form>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-actions align="right">
                    <q-btn v-if="isEditing" icon="delete" label="<?php echo th('common.delete'); ?>" @click="handleDelete">
                    </q-btn>
                    <q-space></q-space>
                    <q-btn label="<?php echo th('common.cancel'); ?>" @click="closeForm"></q-btn>
                    <q-btn label="<?php echo th('common.save'); ?>" color="primary" @click="handleSubmit" :loading="saving">
                    </q-btn>
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>

    <!-- Vue 3 + Quasar -->
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>

    <?php require_once __DIR__ . '/auth-guard.php'; ?>

    <script>
        const app = Vue.createApp({
            data() {
                return {
                    // Data
                    entries: [],
                    loading: false,
                    filter: '',

                    // Auth
                    authToken: '',

                    // Form
                    showForm: false,
                    selectedItem: null,
                    form: {
                        xxx: null,
                        title: '',
                        body: '',
                        tags: [],
                        tagsString: '',
                        active: true,
                        fmd: 'b'
                    },
                    fmdOptions: [
                        { value: 'b', label: <?php echo tj('kbase.fmd_both'); ?> },
                        { value: 'o', label: <?php echo tj('kbase.fmd_organic'); ?> },
                        { value: 'c', label: <?php echo tj('kbase.fmd_conventional'); ?> }
                    ],
                    errors: {
                        title: null,
                        body: null
                    },
                    saving: false,

                    // API
                    API_URL: '<?php echo ECO_API_URL; ?>'
                };
            },
            computed: {
                isEditing() {
                    return !!this.form.xxx;
                },
                columns() {
                    return [{
                            name: 'xxx',
                            label: <?php echo tj('kbase.col_id'); ?>,
                            field: 'xxx',
                            align: 'left',
                            sortable: true,
                            style: 'width: 60px;'
                        },
                        {
                            name: 'title',
                            label: <?php echo tj('kbase.col_title'); ?>,
                            field: 'title',
                            align: 'left',
                            sortable: true
                        },
                        {
                            name: 'tags',
                            label: <?php echo tj('kbase.col_tags'); ?>,
                            field: 'tags',
                            align: 'left',
                            sortable: true
                        },
                        {
                            name: 'active',
                            label: <?php echo tj('kbase.col_status'); ?>,
                            field: 'active',
                            align: 'left',
                            sortable: true,
                            style: 'width: 80px;'
                        },
                        {
                            name: 'created_at',
                            label: <?php echo tj('kbase.col_created'); ?>,
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

                parseTags(tagsStr) {
                    if (!tagsStr) return [];
                    if (Array.isArray(tagsStr)) return tagsStr;
                    // Tags may arrive as a real array or as the brace form the
                    // API sometimes returns them in: {tag1,tag2}
                    if (typeof tagsStr === 'string') {
                        const cleaned = tagsStr.replace(/^\{/, '').replace(/\}$/, '');
                        if (!cleaned) return [];
                        return cleaned.split(',').map(t => t.trim()).filter(Boolean);
                    }
                    return [];
                },

                getTagColor(tag) {
                    const colors = ['primary', 'secondary', 'accent', 'purple', 'deep-orange', 'teal', 'indigo', 'pink'];
                    let hash = 0;
                    for (let i = 0; i < tag.length; i++) {
                        hash = tag.charCodeAt(i) + ((hash << 5) - hash);
                    }
                    return colors[Math.abs(hash) % colors.length];
                },

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    return d.toLocaleDateString('pt-PT', {
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

                async fetchEntries() {
                    this.loading = true;
                    try {
                        const url = this.API_URL + 'kbase.php?action=list';
                        const response = await fetch(url, {
                            headers: this.getRequestHeaders()
                        });

                        if (response.status === 401 || response.status === 403) {
                            this.showNotification(<?php echo tj('kbase.admin_only'); ?>, 'negative', 'lock');
                            setTimeout(() => {
                                window.location.href = 'databases.php';
                            }, 1500);
                            return;
                        }

                        const data = await response.json();
                        if (data.success && data.data) {
                            this.entries = data.data;
                        } else {
                            this.entries = [];
                        }
                    } catch (error) {
                        console.error('Error fetching knowledge base entries:', error);
                        this.entries = [];
                        this.showNotification(<?php echo tj('kbase.load_failed'); ?>, 'negative', 'cloud_off');
                    } finally {
                        this.loading = false;
                    }
                },

                openForm(item = null) {
                    this.selectedItem = item;
                    if (item) {
                        this.form = {
                            xxx: item.xxx || null,
                            title: item.title || '',
                            body: item.body || '',
                            tags: this.parseTags(item.tags),
                            tagsString: '',
                            active: item.active !== false,
                            fmd: item.fmd || 'b'
                        };
                    } else {
                        this.form = {
                            xxx: null,
                            title: '',
                            body: '',
                            tags: [],
                            tagsString: '',
                            active: true,
                            fmd: 'b'
                        };
                    }
                    this.errors = {
                        title: null,
                        body: null
                    };
                    this.showForm = true;
                },

                closeForm() {
                    this.showForm = false;
                    this.selectedItem = null;
                },

                addTagFromInput() {
                    const raw = this.form.tagsString.trim();
                    if (!raw) return;
                    const newTags = raw.split(',').map(t => t.trim().toLowerCase()).filter(Boolean);
                    for (const tag of newTags) {
                        if (!this.form.tags.includes(tag)) {
                            this.form.tags.push(tag);
                        }
                    }
                    this.form.tagsString = '';
                },

                handleDelete() {
                    if (!this.form.xxx) return;
                    this.$q.dialog({
                            title: <?php echo tj('common.confirm_delete'); ?>,
                            message: <?php echo tj('kbase.delete_confirm'); ?>,
                            cancel: true,
                            persistent: true
                        })
                        .onOk(() => {
                            this.performDelete(false);
                        });
                },

                performDelete(permanent) {
                    const url = this.API_URL + 'kbase.php?action=delete';
                    fetch(url, {
                            method: 'POST',
                            headers: {
                                ...this.getRequestHeaders(),
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                id: this.form.xxx,
                                permanent: permanent || false
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification(<?php echo tj('kbase.deleted'); ?>, 'positive', 'check');
                                this.closeForm();
                                this.fetchEntries();
                            } else {
                                this.showNotification(data.message || <?php echo tj('kbase.delete_failed'); ?>, 'negative', 'error');
                            }
                        })
                        .catch(() => this.showNotification(<?php echo tj('common.network_error'); ?>, 'negative', 'cloud_off'));
                },

                async handleSubmit() {
                    this.errors = {
                        title: null,
                        body: null
                    };

                    if (!this.form.title || !this.form.title.trim()) {
                        this.errors.title = <?php echo tj('common.required_field'); ?>;
                    }
                    if (!this.form.body || !this.form.body.trim()) {
                        this.errors.body = <?php echo tj('common.required_field'); ?>;
                    }
                    if (this.errors.title || this.errors.body) return;

                    this.saving = true;
                    try {
                        const action = this.form.xxx ? 'update' : 'add';
                        const payload = {
                            title: this.form.title.trim(),
                            body: this.form.body.trim(),
                            tags: this.form.tags,
                            active: this.form.active,
                            fmd: this.form.fmd
                        };

                        if (action === 'update') {
                            payload.id = this.form.xxx;
                        }

                        const url = this.API_URL + 'kbase.php?action=' + action;
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
                            this.showNotification(action === 'add' ? <?php echo tj('kbase.created'); ?> : <?php echo tj('kbase.updated'); ?>, 'positive', 'check');
                            this.closeForm();
                            this.fetchEntries();
                        } else {
                            this.showNotification(data.message || <?php echo tj('kbase.save_failed'); ?>, 'negative', 'error');
                        }
                    } catch (e) {
                        this.showNotification(<?php echo tj('kbase.error_prefix'); ?> + e.message, 'negative', 'error');
                    } finally {
                        this.saving = false;
                    }
                },

                handleRowClick(event, row) {
                    this.openForm(row);
                }
            },
            mounted() {
                // Initialize auth token
                this.authToken = localStorage.getItem('auth_token') || '';

                if (!AuthGuard.requireSuperuser(this.authToken, (m, c, i) => this.showNotification(m, c, i), 'databases.php')) return;

                this.fetchEntries();
            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>

</html>