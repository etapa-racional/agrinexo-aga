<?php
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('change_password.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        body {
            background: #f4f7f9;
        }

        .password-panel {
            max-width: 640px;
            margin: 0 auto;
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
                        <q-icon name="key" size="md" class="q-mr-sm"></q-icon>
                        <?php echo th('change_password.toolbar'); ?>
                    </q-toolbar-title>
                    <q-space></q-space>
                </q-toolbar>
            </q-header>
            <q-page-container>
                <q-page class="q-pa-md">
                    <div class="password-panel">
                        <q-card>
                            <q-card-section>
                                <div class="text-h6"><?php echo th('change_password.heading'); ?></div>
                                <div class="text-body2 text-grey-7 q-mt-xs"><?php echo th('change_password.rule'); ?></div>
                            </q-card-section>
                            <q-card-section>
                                <q-input v-model="currentPassword" label="<?php echo th('change_password.current'); ?>" type="password" outlined maxlength="20" class="q-mb-md"></q-input>
                                <q-input v-model="newPassword" label="<?php echo th('change_password.new'); ?>" type="password" outlined maxlength="20" hint="<?php echo th('change_password.new_hint'); ?>" class="q-mb-md"></q-input>
                                <q-input v-model="confirmPassword" label="<?php echo th('change_password.confirm'); ?>" type="password" outlined maxlength="20"
                                    :error="confirmPassword !== '' && confirmPassword !== newPassword"
                                    error-message="<?php echo th('common.passwords_mismatch'); ?>"></q-input>
                            </q-card-section>
                            <q-card-actions align="right">
                                <q-btn color="primary" icon="save" label="<?php echo th('common.save'); ?>" :loading="busy" :disable="busy || !canSave" @click="save"></q-btn>
                            </q-card-actions>
                        </q-card>
                    </div>
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
                    apiUrl: '<?php echo ECO_API_URL; ?>change-password.php',
                    currentPassword: '',
                    newPassword: '',
                    confirmPassword: '',
                    busy: false
                };
            },
            computed: {
                canSave() {
                    return this.currentPassword !== '' && this.newPassword !== '' && this.newPassword === this.confirmPassword;
                }
            },
            methods: {
                token() {
                    return localStorage.getItem('auth_token');
                },
                headers() {
                    return {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + this.token()
                    };
                },
                notify(message, color = 'positive') {
                    this.$q.notify({
                        message,
                        color,
                        position: 'top'
                    });
                },
                async save() {
                    if (!this.canSave) return;
                    this.busy = true;
                    try {
                        const response = await fetch(this.apiUrl, {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({
                                current_password: this.currentPassword,
                                new_password: this.newPassword
                            })
                        });
                        if (response.status === 401) {
                            window.location.href = 'login.php';
                            return;
                        }
                        const data = await response.json();
                        if (!response.ok || !data.success) throw new Error(data.message || <?php echo tj('change_password.failed'); ?>);
                        this.notify(<?php echo tj('change_password.success'); ?>);
                        this.currentPassword = '';
                        this.newPassword = '';
                        this.confirmPassword = '';
                    } catch (error) {
                        this.notify(error.message, 'negative');
                    } finally {
                        this.busy = false;
                    }
                },
                goBack() {
                    window.location.href = 'databases.php';
                }
            }
        });
        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>

</html>