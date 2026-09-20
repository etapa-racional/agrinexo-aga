<?php
// goa/app/register.php - Registration page using Quasar Framework UMD
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('register.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 420px;
            width: 90%;
            padding-top: 20px;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
    <div id="q-app">
        <q-layout>
            <q-page-container>
                <q-page class="login-container">
                    <q-card class="login-card q-pa-xl shadow-24 rounded-borders">
                        <!-- Registration Form -->
                        <q-card-section v-if="!registered">
                            <q-form @submit.prevent="handleRegister">
                                <!-- Name Field -->
                                <q-input
                                    v-model="name"
                                    label="<?php echo th('common.name'); ?>"
                                    outlined
                                    dense
                                    clearable
                                    class="q-mb-md"
                                ></q-input>

                                <!-- Username Field -->
                                <q-input
                                    v-model="username"
                                    label="<?php echo th('common.username_or_email'); ?>"
                                    outlined
                                    dense
                                    clearable
                                    :rules="[val => val && val.length >= 5 || <?php echo tv('register.min_5_chars'); ?>]"
                                    lazy-rules
                                ></q-input>

                                <!-- Password Field -->
                                <q-input
                                    v-model="password"
                                    label="<?php echo th('common.password'); ?>"
                                    type="password"
                                    outlined
                                    dense
                                    clearable
                                    class="q-mt-md"
                                    :rules="[val => val && val.length >= 6 || <?php echo tv('common.min_6_chars'); ?>]"
                                    lazy-rules
                                ></q-input>

                                <!-- Confirm Password Field -->
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

                                <!-- CAPTCHA -->
                                <div class="q-mt-md">
                                    <img
                                        :src="captchaUrl"
                                        alt="<?php echo th('register.captcha_label'); ?>"
                                        width="250"
                                        height="50"
                                    >
                                </div>
                                <q-btn
                                    icon="refresh"
                                    label="<?php echo th('register.captcha_refresh'); ?>"
                                    aria-label="<?php echo th('register.captcha_aria'); ?>"
                                    class="q-mt-sm"
                                    @click="refreshCaptcha"
                                ></q-btn>
                                <q-input
                                    v-model="captcha"
                                    label="<?php echo th('register.captcha_label'); ?>"
                                    outlined
                                    dense
                                    maxlength="5"
                                    class="q-mt-sm"
                                    :rules="[val => val && val.length === 5 || <?php echo tv('register.captcha_required'); ?>]"
                                    lazy-rules
                                ></q-input>

                                <!-- AI consent. Both on by default; opt-out. -->
                                <div class="q-mt-lg">
                                    <q-toggle v-model="ai" label="<?php echo th('consent.ai'); ?>"></q-toggle>
                                    <div class="text-caption text-grey-7 q-ml-lg"><?php echo th('consent.ai_hint'); ?></div>
                                    <q-toggle v-model="webmcp" label="<?php echo th('consent.webmcp'); ?>" class="q-mt-sm"></q-toggle>
                                    <div class="text-caption text-grey-7 q-ml-lg"><?php echo th('consent.webmcp_hint'); ?></div>
                                </div>

                                <!-- Register Button -->
                                <q-btn
                                    type="submit"
                                    color="primary"
                                    label="<?php echo th('register.submit'); ?>"
                                    class="full-width q-mt-lg"
                                    size="lg"
                                    :loading="loading"
                                    :disable="loading"
                                ></q-btn>
                            </q-form>
                        </q-card-section>

                        <!-- Post-registration message -->
                        <q-card-section v-else class="text-center">
                            <q-icon name="mark_email_read" size="64px" color="positive"></q-icon>
                            <div class="text-body1 q-mt-md"><?php echo th('register.check_email'); ?></div>
                        </q-card-section>

                        <!-- Footer -->
                        <q-card-section class="text-center q-mt-sm">
                            <q-btn label="<?php echo th('register.login_link'); ?>" @click="navigateTo('login.php')"></q-btn>
                        </q-card-section>
                        <q-card-section class="text-center q-pt-none">
                            <div class="text-grey-6 text-caption">
                                © <?php echo date('Y'); ?> Agrinexo
                            </div>
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
        // Initialize Vue + Quasar app
        const app = Vue.createApp({
            data() {
                return {
                    name: '',
                    username: '',
                    password: '',
                    confirmPassword: '',
                    captcha: '',
                    ai: false,
                    webmcp: false,
                    loading: false,
                    registered: false,
                    captchaUrl: '<?php echo ECO_API_URL; ?>AGNGERGIM.php?ts=' + Date.now(),

                    // API endpoint
                    registerUrl: '<?php echo ECO_API_URL; ?>register.php'
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

                navigateTo(page) {
                    window.location.href = page;
                },

                refreshCaptcha() {
                    this.captcha = '';
                    this.captchaUrl = '<?php echo ECO_API_URL; ?>AGNGERGIM.php?ts=' + Date.now();
                },

                // Handle registration form submission
                async handleRegister() {
                    // Validate inputs
                    if (!this.username || !this.password) {
                        this.showNotification(<?php echo tj('common.fill_all_fields'); ?>, 'warning', 'warning');
                        return;
                    }
                    if (this.password !== this.confirmPassword) {
                        this.showNotification(<?php echo tj('common.passwords_mismatch'); ?>, 'warning', 'warning');
                        return;
                    }

                    this.loading = true;

                    try {
                        const response = await fetch(this.registerUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                name: this.name,
                                username: this.username,
                                password: this.password,
                                confirm_password: this.confirmPassword,
                                captcha: this.captcha,
                                ai: this.ai,
                                webmcp: this.webmcp
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.registered = true;
                            this.showNotification(<?php echo tj('register.created'); ?>, 'positive', 'check_circle');
                        } else {
                            const errorMsg = data.message || <?php echo tj('register.failed'); ?>;
                            this.showNotification(errorMsg, 'negative', 'error');
                            this.refreshCaptcha();
                        }
                    } catch (error) {
                        console.error('Registration error:', error);
                        this.showNotification(<?php echo tj('common.connection_error'); ?>, 'negative', 'cloud_off');
                    } finally {
                        this.loading = false;
                    }
                }
            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>
</html>
