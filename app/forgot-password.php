<?php
// goa/app/forgot-password.php - Request a password reset link
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('forgot_password.page_title');
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
                                <q-icon name="lock_reset" size="48px" color="white"></q-icon>
                            </q-avatar>
                            <div class="text-h5 text-primary q-mt-md">Agrinexo</div>
                            <div class="text-subtitle1 text-grey-7"><?php echo th('forgot_password.subtitle'); ?></div>
                        </q-card-section>

                        <q-separator></q-separator>

                        <!-- Request Form -->
                        <q-card-section v-if="!sent">
                            <q-form @submit.prevent="handleSubmit">
                                <q-input
                                    v-model="username"
                                    label="<?php echo th('common.username_or_email'); ?>"
                                    outlined
                                    dense
                                    clearable
                                    :rules="[val => val && val.length > 0 || <?php echo tv('common.required_field'); ?>]"
                                    lazy-rules
                                ></q-input>

                                <q-btn
                                    type="submit"
                                    color="primary"
                                    label="<?php echo th('forgot_password.submit'); ?>"
                                    class="full-width q-mt-lg"
                                    size="lg"
                                    :loading="loading"
                                    :disable="loading"
                                ></q-btn>
                            </q-form>
                        </q-card-section>

                        <!-- Confirmation message -->
                        <q-card-section v-else class="text-center">
                            <q-icon name="mark_email_read" size="64px" color="positive"></q-icon>
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
                    username: '',
                    loading: false,
                    sent: false,
                    message: '',
                    forgotUrl: '<?php echo ECO_API_URL; ?>forgot-password.php'
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
                    if (!this.username) {
                        this.showNotification(<?php echo tj('forgot_password.fill_field'); ?>, 'warning', 'warning');
                        return;
                    }

                    this.loading = true;

                    try {
                        const response = await fetch(this.forgotUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                username: this.username
                            })
                        });

                        const data = await response.json();
                        this.message = data.message || <?php echo tj('forgot_password.sent_fallback'); ?>;
                        this.sent = true;
                    } catch (error) {
                        console.error('Forgot password error:', error);
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
