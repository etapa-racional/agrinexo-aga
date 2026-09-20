<?php
// goa/app/verify-email.php - Email confirmation landing page
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('verify_email.page_title');
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
                    <q-card class="login-card q-pa-xl shadow-24 rounded-borders text-center">
                        <q-linear-progress v-if="loading" indeterminate color="primary" class="q-mb-md"></q-linear-progress>
                        <q-icon v-else :name="success ? 'check_circle' : 'error'" :color="success ? 'positive' : 'negative'" size="64px"></q-icon>
                        <div class="text-body1 q-mt-md">{{ message }}</div>
                        <q-btn v-if="!loading" label="<?php echo th('verify_email.go_to_login'); ?>" class="q-mt-lg" @click="navigateTo('login.php')"></q-btn>
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
                    loading: true,
                    success: false,
                    message: '',
                    verifyUrl: '<?php echo ECO_API_URL; ?>verify-email.php'
                };
            },
            methods: {
                navigateTo(page) {
                    window.location.href = page;
                }
            },
            async mounted() {
                const params = new URLSearchParams(window.location.search);
                const aut = params.get('aut');
                const vfr = params.get('vfr');

                if (!aut || !vfr) {
                    this.loading = false;
                    this.message = <?php echo tj('verify_email.invalid_link'); ?>;
                    return;
                }

                try {
                    const response = await fetch(`${this.verifyUrl}?aut=${encodeURIComponent(aut)}&vfr=${encodeURIComponent(vfr)}`);
                    const data = await response.json();
                    this.success = !!data.success;
                    this.message = data.message || (this.success ? <?php echo tj('verify_email.activated'); ?> : <?php echo tj('verify_email.failed'); ?>);
                } catch (error) {
                    console.error('Verification error:', error);
                    this.message = <?php echo tj('common.connection_error'); ?>;
                } finally {
                    this.loading = false;
                }
            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>
</html>
