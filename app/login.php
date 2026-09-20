<?php
// goa/app/login.php - Login page using Quasar Framework UMD
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('login.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">
<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        .login-container {
            height: 100vh;
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
        .login-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }
        .login-title {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .login-subtitle {
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div id="q-app">
        <q-layout>
            <q-page-container>
                <q-page class="login-container">
                    <q-card class="login-card q-pa-xl shadow-24 rounded-borders">
                        <!-- Login Form -->
                        <q-card-section>
                            <q-form @submit.prevent="handleLogin">
                                <!-- Username Field -->
                                <q-input
                                    v-model="username"
                                    label="<?php echo th('login.username'); ?>"
                                    outlined
                                    dense
                                    clearable
                                    :rules="[val => val && val.length > 0 || <?php echo tv('common.required_field'); ?>]"
                                    lazy-rules
                                >
                                    <prepend-icon name="person"></prepend-icon>
                                </q-input>

                                <!-- Password Field -->
                                <q-input
                                    v-model="password"
                                    label="<?php echo th('common.password'); ?>"
                                    type="password"
                                    outlined
                                    dense
                                    clearable
                                    :rules="[val => val && val.length > 0 || <?php echo tv('common.required_field'); ?>]"
                                    lazy-rules
                                    class="q-mt-md"
                                >
                                    <prepend-icon name="lock"></prepend-icon>
                                </q-input>

                                <!-- Remember Me Checkbox -->
                                <div class="row q-col-gutter-sm q-mt-md">
                                    <div class="col-6">
                                        <q-checkbox
                                            v-model="rememberMe"
                                            label="<?php echo th('login.remember'); ?>"
                                            size="md"
                                        ></q-checkbox>
                                    </div>
                                    <div class="col-6 text-right">
                                        <q-btn
                                            label="<?php echo th('login.forgot'); ?>"
                                            @click="navigateTo('forgot-password.php')"
                                        ></q-btn>
                                    </div>
                                </div>

                                <!-- Login Button -->
                                <q-btn
                                    type="submit"
                                    color="primary"
                                    label="<?php echo th('login.submit'); ?>"
                                    class="full-width q-mt-lg"
                                    size="lg"
                                    :loading="loading"
                                    :disable="loading"
                                ></q-btn>
                            </q-form>
                        </q-card-section>

                        <!-- Register link -->
                        <q-card-section class="text-center q-mt-sm">
                            <q-btn label="<?php echo th('login.register_link'); ?>" @click="navigateTo('register.php')"></q-btn>
                        </q-card-section>

                        <!-- Footer -->
                        <q-card-section class="text-center q-pt-none">
                            <div class="text-grey-6 text-caption">
                                © 2026 ETAPA RACIONAL
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
                    username: '',
                    password: '',
                    rememberMe: false,
                    loading: false,
                    
                    
                    // API endpoint
                    loginUrl: '<?php echo ECO_API_URL; ?>login.php'
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
                
                // Handle login form submission
                async handleLogin() {
                    // Validate inputs
                    if (!this.username || !this.password) {
                        this.showNotification(<?php echo tj('common.fill_all_fields'); ?>, 'warning', 'warning');
                        return;
                    }
                    
                    this.loading = true;
                    
                    try {
                        const response = await fetch(this.loginUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                username: this.username,
                                password: this.password
                            })
                        });
                        
                        const data = await response.json();
                        
                        if (response.ok && data.userToken) {
                            // Store JWT token
                            localStorage.setItem('auth_token', data.userToken);

                            // Let the parent shell (if any) refresh its role-aware menu immediately
                            if (window.parent && typeof window.parent.refreshMenuAuthState === 'function') {
                                window.parent.refreshMenuAuthState();
                            }

                            // Store credentials if "remember me" is checked
                            if (this.rememberMe) {
                                localStorage.setItem('remembered_username', this.username);
                            } else {
                                localStorage.removeItem('remembered_username');
                            }
                            
                            this.showNotification(<?php echo tj('login.success'); ?>, 'positive', 'check_circle');
                            
                            // Redirect to databases.php after a short delay
                            setTimeout(() => {
                                window.location.href = 'databases.php';
                            }, 1000);
                        } else {
                            const errorMsg = data.message || data.error || <?php echo tj('login.invalid_credentials'); ?>;
                            this.showNotification(errorMsg, 'negative', 'error');
                        }
                    } catch (error) {
                        console.error('Login error:', error);
                        this.showNotification(<?php echo tj('common.connection_error'); ?>, 'negative', 'cloud_off');
                    } finally {
                        this.loading = false;
                    }
                },
            },
            
            // Check for remembered username on mount
            mounted() {
                // Anything that lands here is signed out, including a device
                // bounced by a 401. Dropping the token stops a revoked one
                // being resurrected if the setting is put back.
                localStorage.removeItem('auth_token');

                const rememberedUsername = localStorage.getItem('remembered_username');
                if (rememberedUsername) {
                    this.username = rememberedUsername;
                    this.rememberMe = true;
                }
            }
        });
        
        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>
</html>