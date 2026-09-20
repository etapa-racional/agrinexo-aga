<?php
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('subscribe.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">
<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        body { background: #f4f7f9; }
        .subscription-panel { max-width: 960px; margin: 0 auto; }
    </style>
</head>
<body>
<div id="q-app">
    <q-layout view="hHh lpR fFf">
        <q-header class="ag-base-q-header">
            <q-toolbar>
                <q-btn flat round icon="arrow_back" @click="goBack" aria-label="<?php echo th('common.back'); ?>"></q-btn>
                <q-toolbar-title><?php echo th('subscribe.toolbar'); ?></q-toolbar-title>
            </q-toolbar>
        </q-header>
        <q-page-container>
            <q-page class="q-pa-md">
                <div class="subscription-panel">
                    <q-card class="q-mb-md">
                        <q-card-section>
                            <div class="text-h6"><?php echo th('subscribe.create_heading'); ?></div>
                            <div class="text-body2 text-grey-7 q-mt-sm"><?php echo th('subscribe.create_body'); ?></div>
                            <q-input v-model="description" label="<?php echo th('subscribe.farm_name'); ?>" outlined class="q-mt-md" maxlength="255"></q-input>
                            <q-checkbox v-model="organic" label="<?php echo th('subscribe.farming_organic'); ?>" class="q-mt-sm"></q-checkbox>
                            <div class="text-body2 q-mt-md"><?php echo th('subscribe.include'); ?></div>
                            <div class="column">
                                <q-checkbox v-model="includeProductions" label="<?php echo th('subscribe.include_productions'); ?>"></q-checkbox>
                                <q-checkbox v-model="includeInputs" label="<?php echo th('subscribe.include_inputs'); ?>"></q-checkbox>
                                <q-checkbox v-model="includeSample" :disable="!includeProductions || !includeInputs" label="<?php echo th('subscribe.include_sample'); ?>"></q-checkbox>
                            </div>
                        </q-card-section>
                        <q-card-actions align="right">
                            <q-btn color="primary" icon="science" label="<?php echo th('subscribe.start_trial'); ?>" :loading="busy" :disable="busy" @click="startTrial"></q-btn>
                        </q-card-actions>
                    </q-card>

                    <q-card>
                        <q-card-section>
                            <div class="text-h6">{{ allUsers ? <?php echo tj('subscribe.all_subs'); ?> : <?php echo tj('subscribe.my_subs'); ?> }}</div>
                            <div v-if="allUsers" class="text-body2 text-grey-7 q-mt-xs"><?php echo th('subscribe.admin_note'); ?></div>
                        </q-card-section>
                        <q-list separator>
                            <q-item v-for="subscription in subscriptions" :key="subscription.id">
                                <q-item-section avatar><q-icon name="storage" color="primary"></q-icon></q-item-section>
                                <q-item-section>
                                    <q-item-label>{{ subscription.rfr }}<span v-if="subscription.description" class="text-grey-7"> · {{ subscription.description }}</span></q-item-label>
                                    <q-item-label v-if="allUsers" caption><?php echo th('subscribe.user_label'); ?> {{ subscription.owner_name || subscription.owner_username || ('#' + subscription.user_id) }}</q-item-label>
                                    <q-item-label caption>{{ subscription.kind === 'trial' ? <?php echo tj('subscribe.kind_trial'); ?> : <?php echo tj('subscribe.kind_paid'); ?> }} · {{ statusLabels[subscription.status] || subscription.status }}</q-item-label>
                                    <q-item-label caption><?php echo th('subscribe.expires'); ?> {{ formatDate(subscription.expires_at) }}</q-item-label>
                                </q-item-section>
                                <q-item-section side>
                                    <q-btn v-if="subscription.status === 'active' || subscription.status === 'trial_active'" color="primary" icon="open_in_new" label="<?php echo th('common.connect'); ?>" :disable="busy" @click="connect(subscription)"></q-btn>
                                    <q-btn v-if="allUsers" icon="tune" label="<?php echo th('subscribe.manage'); ?>" :disable="busy" @click="openManage(subscription)"></q-btn>
                                    <q-btn v-if="allUsers" icon="restart_alt" label="<?php echo th('subscribe.reset'); ?>" :disable="busy" @click="openReset(subscription)"></q-btn>
                                    <q-btn icon="delete_forever" label="<?php echo th('subscribe.delete_db'); ?>" :disable="busy" @click="promptDelete(subscription)"></q-btn>
                                </q-item-section>
                            </q-item>
                            <q-item v-if="subscriptions.length === 0"><q-item-section class="text-grey-7"><?php echo th('subscribe.empty'); ?></q-item-section></q-item>
                        </q-list>
                    </q-card>
                </div>

                <!-- Superuser only; the server enforces it. -->
                <q-dialog v-model="manageDialogOpen" persistent>
                    <q-card class="form-card" style="min-width: 360px;">
                        <q-card-section class="modal-header row items-center">
                            <div class="text-h6"><?php echo th('subscribe.manage'); ?></div>
                            <q-space></q-space>
                            <q-btn icon="close" flat round dense v-close-popup></q-btn>
                        </q-card-section>
                        <q-card-section class="q-pt-md form-scroll">
                            <div class="text-body2 q-mb-md"><strong>{{ manageTarget?.rfr }}</strong></div>
                            <q-input v-model="manageForm.description" outlined dense maxlength="255" class="q-mb-md"
                                label="<?php echo th('subscribe.farm_name'); ?>"></q-input>
                            <q-select v-model="manageForm.kind" :options="kindOptions" emit-value map-options outlined dense class="q-mb-md"
                                label="<?php echo th('subscribe.manage_kind'); ?>"></q-select>
                            <q-input v-model="manageForm.expires_on" type="date" outlined dense stack-label
                                label="<?php echo th('subscribe.expires'); ?>"></q-input>
                        </q-card-section>
                        <q-card-actions align="right">
                            <q-btn label="<?php echo th('common.cancel'); ?>" v-close-popup></q-btn>
                            <q-btn color="primary" label="<?php echo th('common.save'); ?>" :loading="busy" :disable="busy || !manageForm.kind || !manageForm.expires_on" @click="saveManage"></q-btn>
                        </q-card-actions>
                    </q-card>
                </q-dialog>

                <q-dialog v-model="resetDialogOpen" persistent>
                    <q-card class="form-card" style="min-width: 360px;">
                        <q-card-section class="modal-header row items-center">
                            <div class="text-h6"><?php echo th('subscribe.reset'); ?></div>
                            <q-space></q-space>
                            <q-btn icon="close" flat round dense v-close-popup></q-btn>
                        </q-card-section>
                        <q-card-section class="q-pt-md form-scroll">
                            <div class="text-body2 q-mb-sm"><strong>{{ resetTarget?.rfr }}</strong></div>
                            <div class="text-body2 q-mb-md"><?php echo th('subscribe.reset_body'); ?></div>
                            <div class="column">
                                <q-checkbox v-model="resetForm.climate" label="<?php echo th('subscribe.reset_climate'); ?>"></q-checkbox>
                                <q-checkbox v-model="resetForm.weather" label="<?php echo th('subscribe.reset_weather'); ?>"></q-checkbox>
                                <q-checkbox v-model="resetForm.satellite" label="<?php echo th('subscribe.reset_satellite'); ?>"></q-checkbox>
                            </div>
                        </q-card-section>
                        <q-card-actions align="right">
                            <q-btn label="<?php echo th('common.cancel'); ?>" v-close-popup></q-btn>
                            <q-btn color="negative" icon="restart_alt" label="<?php echo th('subscribe.reset'); ?>" :loading="busy" :disable="busy || !resetSelected" @click="confirmReset"></q-btn>
                        </q-card-actions>
                    </q-card>
                </q-dialog>

                <q-dialog v-model="deleteDialogOpen" persistent>
                    <q-card class="form-card" style="min-width: 360px;">
                        <q-card-section class="modal-header row items-center">
                            <div class="text-h6"><?php echo th('subscribe.delete_title'); ?></div>
                            <q-space></q-space>
                            <q-btn icon="close" flat round dense v-close-popup @click="cancelDelete"></q-btn>
                        </q-card-section>
                        <q-card-section class="q-pt-none form-scroll">
                            <div class="text-body2">
                                <?php echo th('subscribe.delete_warning_1'); ?> <strong>{{ deleteTarget?.rfr }}</strong><?php echo th('subscribe.delete_warning_2'); ?>
                            </div>
                            <div class="text-body2 q-mt-md">
                                <?php echo th('subscribe.delete_confirm_1'); ?> <strong>{{ deleteTarget?.rfr }}</strong> <?php echo th('subscribe.delete_confirm_2'); ?>
                            </div>
                            <q-input v-model="deleteConfirmText" outlined dense class="q-mt-sm" autofocus></q-input>
                        </q-card-section>
                        <q-card-actions align="right">
                            <q-btn label="<?php echo th('common.cancel'); ?>" v-close-popup @click="cancelDelete"></q-btn>
                            <q-btn color="negative" icon="delete_forever" label="<?php echo th('subscribe.delete_title'); ?>" :loading="busy" :disable="busy || !deleteConfirmMatches" @click="confirmDelete"></q-btn>
                        </q-card-actions>
                    </q-card>
                </q-dialog>
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
            apiUrl: '<?php echo ECO_API_URL; ?>subscribe.php',
            subscriptions: [],
            allUsers: false,
            description: '',
            organic: false,
            includeProductions: true,
            includeInputs: true,
            includeSample: true,
            busy: false,
            manageDialogOpen: false,
            manageTarget: null,
            manageForm: { description: '', kind: '', expires_on: '' },
            kindOptions: [
                { value: 'trial', label: <?php echo tj('subscribe.kind_trial'); ?> },
                { value: 'paid', label: <?php echo tj('subscribe.kind_paid'); ?> }
            ],
            statusLabels: {
                trial_active: <?php echo tj('subscribe.status_trial_active'); ?>,
                active: <?php echo tj('subscribe.status_active'); ?>,
                pending_payment: <?php echo tj('subscribe.status_pending_payment'); ?>,
                expired: <?php echo tj('subscribe.status_expired'); ?>
            },
            resetDialogOpen: false,
            resetTarget: null,
            resetForm: { climate: false, weather: false, satellite: false },
            deleteDialogOpen: false,
            deleteTarget: null,
            deleteConfirmText: ''
        };
    },
    watch: {
        // Disabling the checkbox does not clear it, and the server rejects sample
        // data without both catalogues.
        includeProductions(value) { if (!value) this.includeSample = false; },
        includeInputs(value) { if (!value) this.includeSample = false; }
    },
    computed: {
        resetSelected() {
            return this.resetForm.climate || this.resetForm.weather || this.resetForm.satellite;
        },
        deleteConfirmMatches() {
            return !!this.deleteTarget && this.deleteConfirmText.trim() === this.deleteTarget.rfr;
        }
    },
    methods: {
        token() { return localStorage.getItem('auth_token'); },
        headers() { return { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + this.token() }; },
        async request(action, payload = {}) {
            const response = await fetch(this.apiUrl + '?action=' + encodeURIComponent(action), { method: 'POST', headers: this.headers(), body: JSON.stringify(payload) });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || <?php echo tj('subscribe.request_error'); ?>);
            return data;
        },
        notify(message, color = 'positive') { this.$q.notify({ message, color, position: 'top' }); },
        async loadSubscriptions() {
            try {
                const response = await fetch(this.apiUrl + '?action=list', { headers: this.headers() });
                const data = await response.json();
                if (response.status === 401) { window.location.href = 'login.php'; return; }
                this.subscriptions = data.subscriptions || [];
                this.allUsers = !!data.all_users;
            } catch (error) { this.notify(error.message, 'negative'); }
        },
        async startTrial() {
            this.busy = true;
            try { await this.request('trial', { description: this.description, farming: this.organic ? 'o' : 'c', include_productions: this.includeProductions, include_inputs: this.includeInputs, include_sample: this.includeSample }); this.notify(<?php echo tj('subscribe.trial_started'); ?>); await this.loadSubscriptions(); }
            catch (error) { this.notify(error.message, 'negative'); }
            finally { this.busy = false; }
        },
        openManage(subscription) {
            this.manageTarget = subscription;
            this.manageForm = { description: subscription.description || '', kind: subscription.kind, expires_on: (subscription.expires_at || '').slice(0, 10) };
            this.manageDialogOpen = true;
        },
        async saveManage() {
            this.busy = true;
            try {
                await this.request('manage', { subscription_id: this.manageTarget.id, ...this.manageForm });
                this.notify(<?php echo tj('subscribe.managed'); ?>);
                this.manageDialogOpen = false;
                await this.loadSubscriptions();
            } catch (error) { this.notify(error.message, 'negative'); }
            finally { this.busy = false; }
        },
        openReset(subscription) {
            this.resetTarget = subscription;
            this.resetForm = { climate: false, weather: false, satellite: false };
            this.resetDialogOpen = true;
        },
        async confirmReset() {
            if (!this.resetSelected) return;
            this.busy = true;
            try {
                await this.request('reset-data', { subscription_id: this.resetTarget.id, ...this.resetForm });
                this.notify(<?php echo tj('subscribe.reset_done'); ?>);
                this.resetDialogOpen = false;
            } catch (error) { this.notify(error.message, 'negative'); }
            finally { this.busy = false; }
        },
        promptDelete(subscription) { this.deleteTarget = subscription; this.deleteConfirmText = ''; this.deleteDialogOpen = true; },
        cancelDelete() { this.deleteDialogOpen = false; this.deleteTarget = null; this.deleteConfirmText = ''; },
        async confirmDelete() {
            if (!this.deleteConfirmMatches) return;
            this.busy = true;
            try {
                await this.request('delete', { subscription_id: this.deleteTarget.id });
                this.notify(<?php echo tj('subscribe.deleted'); ?>);
                this.deleteDialogOpen = false;
                this.deleteTarget = null;
                this.deleteConfirmText = '';
                await this.loadSubscriptions();
            } catch (error) { this.notify(error.message, 'negative'); }
            finally { this.busy = false; }
        },
        connect(subscription) {
            window.parent.krd = subscription.krd;
            window.parent.dbName = subscription.rfr;
            if (typeof window.parent.refreshMenuAuthState === 'function') {
                window.parent.refreshMenuAuthState();
            }
            window.location.href = 'fields.php';
        },
        formatDate(value) { return value ? new Date(value.replace(' ', 'T')).toLocaleString(<?php echo json_encode(lang_locale()); ?>) : <?php echo tj('subscribe.not_set'); ?>; },
        goBack() { window.location.href = 'databases.php'; }
    },
    mounted() { this.loadSubscriptions(); }
});
app.use(Quasar);
app.mount('#q-app');
</script>
</body>
</html>
