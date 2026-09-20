<?php
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('help.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        body {
            background: #f4f7f9;
        }

        .help-content {
            max-width: 960px;
            margin: 0 auto;
        }

        .markdown-body {
            color: #263238;
            font-size: 1rem;
            line-height: 1.7;
        }

        .markdown-body h1,
        .markdown-body h2,
        .markdown-body h3 {
            color: var(--q-primary);
            line-height: 1.3;
            margin-top: 1.8em;
            margin-bottom: 0.7em;
        }

        .markdown-body h1 {
            font-size: 2rem;
            margin-top: 0;
        }

        .markdown-body h2 {
            border-bottom: 1px solid #e0e6ea;
            padding-bottom: 0.35em;
            font-size: 1.5rem;
        }

        .markdown-body h3 {
            color: #37474f;
            font-size: 1.2rem;
        }

        .markdown-body p {
            margin: 0 0 1em;
        }

        .markdown-body ul,
        .markdown-body ol {
            padding-left: 1.5rem;
            margin: 0 0 1.1em;
        }

        .markdown-body li {
            margin-bottom: 0.35em;
        }

        .markdown-body a {
            color: var(--q-primary);
        }

        .markdown-body strong {
            color: #263238;
        }

        .markdown-body code {
            background: #eef2f5;
            border-radius: 3px;
            color: #37474f;
            padding: 0.12em 0.35em;
        }

        .markdown-body pre {
            background: #263238;
            border-radius: 4px;
            color: #fff;
            overflow-x: auto;
            padding: 1rem;
        }

        .markdown-body pre code {
            background: transparent;
            color: inherit;
            padding: 0;
        }

        .markdown-body blockquote {
            border-left: 4px solid var(--q-primary);
            color: #546e7a;
            margin: 1rem 0;
            padding: 0.2rem 1rem;
        }

        .markdown-body table {
            border-collapse: collapse;
            display: block;
            margin: 1rem 0 1.5rem;
            max-width: 100%;
            overflow-x: auto;
            width: 100%;
        }

        .markdown-body th,
        .markdown-body td {
            border: 1px solid #d9e1e5;
            padding: 0.6rem 0.75rem;
            text-align: left;
        }

        .markdown-body th {
            background: #f1f5f7;
            font-weight: 700;
        }

        .markdown-body hr {
            border: 0;
            border-top: 1px solid #e0e6ea;
            margin: 2rem 0;
        }

        @media (max-width: 599px) {
            .markdown-body {
                font-size: 0.95rem;
            }

            .markdown-body h1 {
                font-size: 1.7rem;
            }

            .markdown-body h2 {
                font-size: 1.3rem;
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
                        <q-icon name="help_outline" size="md" class="q-mr-sm"></q-icon>
                        <?php echo th('help.toolbar'); ?>
                    </q-toolbar-title>
                    <q-space></q-space>
                </q-toolbar>
            </q-header>
            <q-page-container>
                <q-page class="q-pa-md">
                    <main class="help-content">
                        <q-linear-progress
                            v-if="loading"
                            indeterminate
                            color="primary"
                            class="q-mb-md">
                        </q-linear-progress>

                        <q-card flat bordered>
                            <q-card-section v-if="error" class="text-center q-pa-xl">
                                <q-icon name="error_outline" size="56px" color="negative"></q-icon>
                                <div class="text-h6 q-mt-md"><?php echo th('help.error_title'); ?></div>
                                <div class="text-body2 text-grey-7 q-mt-sm">{{ error }}</div>
                                <q-btn
                                    icon="refresh"
                                    label="<?php echo th('common.try_again'); ?>"
                                    class="q-mt-lg"
                                    @click="loadDocumentation">
                                </q-btn>
                            </q-card-section>

                            <q-card-section v-else-if="loading && !htmlContent" class="text-center q-pa-xl">
                                <q-spinner color="primary" size="40px"></q-spinner>
                                <div class="text-body1 text-grey-7 q-mt-md"><?php echo th('help.loading'); ?></div>
                            </q-card-section>

                            <q-card-section v-else class="markdown-body">
                                <div v-html="htmlContent"></div>
                            </q-card-section>
                        </q-card>
                    </main>
                </q-page>
            </q-page-container>
        </q-layout>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@15.0.12/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
    <script>
        const app = Vue.createApp({
            data() {
                return {
                    htmlContent: '',
                    loading: false,
                    error: ''
                };
            },
            methods: {
                async loadDocumentation() {
                    this.loading = true;
                    this.error = '';

                    try {
                        const response = await fetch(<?php echo tj('help.doc_file'); ?>, {
                            cache: 'no-store'
                        });
                        if (!response.ok) {
                            throw new Error(<?php echo tj('help.server_response'); ?> + response.status);
                        }

                        const markdown = await response.text();
                        this.htmlContent = DOMPurify.sanitize(marked.parse(markdown));
                    } catch (error) {
                        console.error('Help document error:', error);
                        this.htmlContent = '';
                        this.error = <?php echo tj('help.offline'); ?>;
                    } finally {
                        this.loading = false;
                    }
                },
                goBack() {
                    if (window.history.length > 1) {
                        window.history.back();
                        return;
                    }
                    window.location.href = 'databases.php';
                }
            },
            mounted() {
                this.loadDocumentation();
            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>

</html>