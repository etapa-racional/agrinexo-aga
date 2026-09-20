<?php
// goa/app/reset-password.php - Set a new password using an emailed token
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('reset_password.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 420px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div id="q-app">
        <q-layout>
            <q-page-container>
                <q-page class="login-container">
                    <q-card class="login-card q-pa-xl shadow-24 rounded-borders">
                        <!-- Logo and Title Section -->
                        <q-card-section class="text-center q-mb-lg">
                            <q-avatar size="80px" class="bg-primary shadow-10">
                                <q-icon name="password" size="48px" color="white"></q-icon>
                            </q-avatar>
                            <div class="text-h5 text-primary q-mt-md">Agrinexo</div>
                            <div class="text-subtitle1 text-grey-7"><?php echo th('reset_password.subtitle'); ?></div>
                        </q-card-section>

                        <q-separator></q-separator>

                        <!-- Reset Form -->
                        <q-card-section v-if="!invalidLink && !done">
                            <q-form @submit.prevent="handleSubmit">
                                <q-input
                                    v-model="password"
                                    label="<?php echo th('reset_password.new_password'); ?>"
                                    type="password"
                                    outlined
                                    dense
                                    clearable
                                    :rules="[val => val && val.length >= 6 || <?php echo tv('common.min_6_chars'); ?>]"
                                    lazy-rules
                                ></q-input>

                                <q-input
                                    v-model="confirmPassword"
                                    label="<?php echo th('common.confirm_password'); ?>"
                                    type="password"
                                    outlined
                                    dense
                                    clearable
                                    class="q-mt-md"
                                    :rules="[val => val === password || <?php echo tv('common.passwords_mismatch'); ?>]"
                                    lazy-rules
                                ></q-input>

                                <q-btn
                                    type="submit"
                                    color="primary"
                                    label="<?php echo th('common.save'); ?>"
                                    class="full-width q-mt-lg"
                                    size="lg"
                                    :loading="loading"
                                    :disable="loading"
                                ></q-btn>
                            </q-form>
                        </q-card-section>

                        <!-- Result message -->
                        <q-card-section v-else class="text-center">
                            <q-icon :name="done ? 'check_circle' : 'error'" :color="done ? 'positive' : 'negative'" size="64px"></q-icon>
                            <div class="text-body1 q-mt-md">{{ message }}</div>
                        </q-card-section>

                        <!-- Footer -->
                        <q-card-section class="text-center q-mt-sm">
                            <q-btn label="<?php echo th('common.back_to_login'); ?>" @click="navigateTo('login.php')"></q-btn>
                        </q-card-section>
                    </q-card>
                </q-page>
            </q-page-container>
        </q-layout>
    </div>

    <!-- Vue.js -->
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <!-- Quasar Framework JS -->
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>

    <script>
        const app = Vue.createApp({
            data() {
                return {
                    password: '',
                    confirmPassword: '',
                    loading: false,
                    invalidLink: false,
                    done: false,
                    message: '',
                    aut: '',
                    vfr: '',
                    resetUrl: '<?php echo ECO_API_URL; ?>reset-password.php'
                };
            },
            methods: {
                navigateTo(page) {
                    window.location.href = page;
                },
                showNotification(message, color, icon) {
                    this.$q.notify({
                        message: message,
                        color: color || 'negative',
                        icon: icon || 'error',
                        position: 'top',
                        timeout: 4000,
                        html: true
                    });
                },
                async handleSubmit() {
                    if (!this.password || !this.confirmPassword) {
                        this.showNotification(<?php echo tj('common.fill_all_fields'); ?>, 'warning', 'warning');
                        return;
                    }
                    if (this.password !== this.confirmPassword) {
                        this.showNotification(<?php echo tj('common.passwords_mismatch'); ?>, 'warning', 'warning');
                        return;
                    }

                    this.loading = true;

                    try {
                        const response = await fetch(this.resetUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                aut: this.aut,
                                vfr: this.vfr,
                                password: this.password
                            })
                        });

                        const data = await response.json();
                        this.message = data.message || '';
                        this.done = !!data.success;
                    } catch (error) {
                        console.error('Reset password error:', error);
                        this.showNotification(<?php echo tj('common.connection_error'); ?>, 'negative', 'cloud_off');
                    } finally {
                        this.loading = false;
                    }
                }
            },
            mounted() {
                const params = new URLSearchParams(window.location.search);
                this.aut = params.get('aut') || '';
                this.vfr = params.get('vfr') || '';
                if (!this.aut || !this.vfr) {
                    this.invalidLink = true;
                    this.message = <?php echo tj('reset_password.invalid_link'); ?>;
                }
            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>
</html>
