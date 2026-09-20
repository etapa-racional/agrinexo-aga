<?php
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('profile.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        body {
            background: #f4f7f9;
        }

        .profile-panel {
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
                    <q-toolbar-title>
                        <q-icon name="person" size="md" class="q-mr-sm"></q-icon>
                        <?php echo th('profile.toolbar'); ?>
                    </q-toolbar-title>
                    <q-space></q-space>
                </q-toolbar>
            </q-header>
            <q-page-container>
                <q-page class="q-pa-md">
                    <div class="profile-panel">
                        <q-card>
                            <q-card-section>
                                <div class="text-h6"><?php echo th('profile.heading'); ?></div>
                                <div class="text-body2 text-grey-7 q-mt-xs"><?php echo th('profile.subheading'); ?></div>
                            </q-card-section>
                            <q-card-section>
                                <q-input v-model="name" label="<?php echo th('common.name'); ?>" outlined maxlength="50" class="q-mb-md"></q-input>
                                <q-input v-model="email" label="<?php echo th('common.email'); ?>" type="email" outlined maxlength="50"></q-input>
                            </q-card-section>
                            <q-separator></q-separator>
                            <q-card-section>
                                <div class="text-subtitle1"><?php echo th('consent.heading'); ?></div>
                                <div class="text-body2 text-grey-7 q-mt-xs q-mb-md"><?php echo th('consent.subheading'); ?></div>
                                <q-toggle v-model="ai" label="<?php echo th('consent.ai'); ?>"></q-toggle>
                                <div class="text-caption text-grey-7 q-ml-lg"><?php echo th('consent.ai_hint'); ?></div>
                                <q-toggle v-model="webmcp" label="<?php echo th('consent.webmcp'); ?>" class="q-mt-sm"></q-toggle>
                                <div class="text-caption text-grey-7 q-ml-lg"><?php echo th('consent.webmcp_hint'); ?></div>
                            </q-card-section>
                            <q-card-actions align="right">
                                <q-btn color="primary" icon="save" label="<?php echo th('common.save'); ?>" :loading="busy" :disable="busy" @click="save"></q-btn>
                            </q-card-actions>
                        </q-card>

                        <!-- Only shown when a consent toggle changed; a name or
                             email edit saves silently. -->
                        <q-dialog v-model="confirmOpen" persistent>
                            <q-card style="min-width: 360px;">
                                <q-card-section class="row items-center">
                                    <div class="text-h6"><?php echo th('consent.title'); ?></div>
                                </q-card-section>
                                <q-card-section class="q-pt-none">
                                    <div class="text-body2"><?php echo th('consent.warning'); ?></div>
                                </q-card-section>
                                <q-card-actions align="right">
                                    <q-btn flat label="<?php echo th('common.cancel'); ?>" @click="cancelConsent"></q-btn>
                                    <q-btn color="negative" label="<?php echo th('consent.confirm'); ?>" :loading="busy" :disable="busy" @click="confirmConsent"></q-btn>
                                </q-card-actions>
                            </q-card>
                        </q-dialog>
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
                    apiUrl: '<?php echo ECO_API_URL; ?>profile.php',
                    name: '',
                    email: '',
                    ai: true,
                    webmcp: true,
                    // What loadProfile() last returned, so Cancel can restore it.
                    loadedAi: true,
                    loadedWebmcp: true,
                    confirmOpen: false,
                    busy: false
                };
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
                async loadProfile() {
                    try {
                        const response = await fetch(this.apiUrl, {
                            headers: this.headers()
                        });
                        if (response.status === 401) {
                            window.location.href = 'login.php';
                            return;
                        }
                        const data = await response.json();
                        if (!response.ok || !data.success) throw new Error(data.message || <?php echo tj('profile.load_failed'); ?>);
                        this.name = data.name || '';
                        this.email = data.email || '';
                        this.ai = data.ai !== false;
                        this.webmcp = data.webmcp !== false;
                        this.loadedAi = this.ai;
                        this.loadedWebmcp = this.webmcp;
                    } catch (error) {
                        this.notify(error.message, 'negative');
                    }
                },
                consentChanged() {
                    return this.ai !== this.loadedAi || this.webmcp !== this.loadedWebmcp;
                },
                save() {
                    if (this.consentChanged()) {
                        this.confirmOpen = true;
                        return;
                    }
                    this.postProfile();
                },
                cancelConsent() {
                    this.ai = this.loadedAi;
                    this.webmcp = this.loadedWebmcp;
                    this.confirmOpen = false;
                },
                async confirmConsent() {
                    await this.postProfile();
                },
                async postProfile() {
                    this.busy = true;
                    try {
                        const response = await fetch(this.apiUrl, {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({
                                name: this.name,
                                email: this.email,
                                ai: this.ai,
                                webmcp: this.webmcp
                            })
                        });
                        if (response.status === 401) {
                            window.location.href = 'login.php';
                            return;
                        }
                        const data = await response.json();
                        if (!response.ok || !data.success) throw new Error(data.message || <?php echo tj('profile.save_failed'); ?>);

                        this.confirmOpen = false;
                        this.loadedAi = this.ai;
                        this.loadedWebmcp = this.webmcp;

                        if (data.signed_out) {
                            this.signOut();
                            return;
                        }
                        this.notify(<?php echo tj('profile.success'); ?>);
                    } catch (error) {
                        this.notify(error.message, 'negative');
                    } finally {
                        this.busy = false;
                    }
                },
                // Every standing token now disagrees with the row, so this
                // session is over too. The shell reload is what unpublishes the
                // WebMCP surface: the bridge cannot be uninstalled live.
                signOut() {
                    localStorage.removeItem('auth_token');
                    try {
                        if (window.parent && window.parent !== window) {
                            window.parent.krd = window.parent.dbName = undefined;
                            window.parent.location.reload();
                            return;
                        }
                    } catch (e) {
                        /* Cross-origin or opened directly; fall through. */
                    }
                    window.location.href = 'login.php';
                },
                goBack() {
                    window.location.href = 'databases.php';
                }
            },
            mounted() {
                this.loadProfile();
            }
        });
        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>

</html>