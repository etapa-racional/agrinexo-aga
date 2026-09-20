<?php

// Config is required relatively: __DIR__ would read the English backend.

$agtCfg = require 'config.php';

// Relative, so it resolves beside whichever instance served this page.
$agtEndpoint = 'assistant.php';

// Trailing slash: call sites append a bare filename.
$agtApiUrl = rtrim((string) ($agtCfg['eco_api_url'] ?? ''), '/') . '/';

$agtAppUrl = rtrim((string) ($agtCfg['app_url'] ?? '../app'), '/');

// Interface language from ?lang=; tool names stay English for the model.
$agtLang = (($_GET['lang'] ?? '') === 'pt') ? 'pt' : 'en';

$agtStrings = [
    'en' => [
        'assistant.page_title'            => 'AI Assistant',
        'assistant.model'                 => 'Model:',
        'assistant.welcome'               => 'Ask anything about agriculture',
        'assistant.from_browser_agent'    => 'Asked by the browser agent',
        'assistant.input_placeholder'     => 'Type your message...',
        'assistant.ai_error'              => 'AI error',
        'assistant.krd_warning'           => 'KRD not found. The assistant may not be able to query the data.',
        'assistant.attach_image'          => 'Attach a photograph',
        'assistant.image_default_caption' => 'Take a look at this photograph.',
        'assistant.proposal_title'        => 'Proposed operation',
        'assistant.proposal_title_crop'   => 'Proposed crop',
        'assistant.proposal_image'        => 'Photograph',
        'assistant.proposal_apply'        => 'Fill the form',
        'assistant.proposal_discard'      => 'Discard',
        'assistant.proposal_no_rate'      => 'rate to be entered',
        'assistant.proposal_no_shell'     => 'The form can only be filled from inside the application shell.',
    ],
    'pt' => [
        'assistant.page_title'            => 'Assistente IA',
        'assistant.model'                 => 'Modelo:',
        'assistant.welcome'               => 'Pergunte qualquer coisa sobre agricultura',
        'assistant.from_browser_agent'    => 'Perguntado pelo agente do navegador',
        'assistant.input_placeholder'     => 'Digite a sua mensagem...',
        'assistant.ai_error'              => 'Erro na IA',
        'assistant.krd_warning'           => 'KRD não encontrado. O assistente pode não conseguir consultar os dados.',
        'assistant.attach_image'          => 'Anexar uma fotografia',
        'assistant.image_default_caption' => 'Veja esta fotografia.',
        'assistant.proposal_title'        => 'Operação proposta',
        'assistant.proposal_title_crop'   => 'Cultura proposta',
        'assistant.proposal_image'        => 'Fotografia',
        'assistant.proposal_apply'        => 'Preencher o formulário',
        'assistant.proposal_discard'      => 'Descartar',
        'assistant.proposal_no_rate'      => 'dose por indicar',
        'assistant.proposal_no_shell'     => 'O formulário só pode ser preenchido a partir da aplicação.',
    ],
];

// tj() emits double quotes, which would end the attribute they sit in.
function t(string $key, array $vars = []): string
{
    $text = $GLOBALS['agtStrings'][$GLOBALS['agtLang']][$key] ?? $key;
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

function th(string $key, array $vars = []): string
{
    return htmlspecialchars(t($key, $vars), ENT_QUOTES, 'UTF-8');
}

function tj(string $key, array $vars = []): string
{
    return json_encode(
        t($key, $vars),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
    );
}

function tv(string $key, array $vars = []): string
{
    return htmlspecialchars("'" . addcslashes(t($key, $vars), "\\'") . "'", ENT_COMPAT, 'UTF-8');
}

$pageTitle = t('assistant.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($agtLang, ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.prod.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900|Material+Icons" rel="stylesheet" type="text/css">

    <style>
        /* Overrides Quasar's own :root block, so it must stay after the link
           above. Everything using color="primary", .bg-primary/.text-primary
           or var(--q-primary) follows. */
        :root {
            --q-secondary: #82B446;
            --q-primary:  #414C33;
        }

        /* The header band this page puts on its <q-header>. */
        .ag-base-q-header {
            background-color: white;
            color: var(--q-primary);
        }

        /* Quasar forces uppercase on button labels; app-head.php turned that
           off app-wide and this page was written expecting it. */
        .q-btn {
            text-transform: none;
        }
    </style>

    <!-- KaTeX: renders LaTeX math (e.g. $K_c$, $ET_c = K_c \times ET_0$) the
         assistant writes in its answers; marked/DOMPurify alone leave it as
         literal text since $...$ isn't markdown syntax. -->
    <link href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" rel="stylesheet">

    <style>
        body {
            margin: 0;
            padding: 0;
        }

        /* .chat-area is the only scroller; Quasar's re-measure and 100vh never settle. */
        .q-layout {
            overflow: hidden;
        }

        /* Model selector bar */
        .model-bar {
            background: #f5f5f5;
            border-bottom: 1px solid #e0e0e0;
        }

        /* Chat area */
        .chat-area {
            height: calc(100vh - 201px); /* header 50 + model bar 57 + input bar 94 */
            overflow-y: auto;
            padding: 16px;
            background: #fafafa;
        }

        /* Gives up the 56px the queued-photograph row takes. */
        .chat-area--with-image {
            height: calc(100vh - 257px);
        }

        /* Message bubbles */
        .message {
            max-width: 80%;
            margin-bottom: 12px;
            clear: both;
        }

        .message.user {
            float: right;
        }

        .message.assistant {
            float: left;
        }

        .message .bubble {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .message.user .bubble {
            background: var(--q-primary);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.assistant .bubble {
            background: white;
            color: #333;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 4px;
        }

        /*
         * The proposal card sits in the message flow, so it is sized like an
         * assistant bubble rather than as a full-width panel: same 80% cap, same
         * clear, same 14px text. Quasar's default card sections are 16px all
         * round, which next to a 10px/14px bubble reads as a much bigger object
         * than the three lines of content warrant.
         */
        .proposal-card {
            max-width: 80%;
            clear: both;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .proposal-card .q-card__section {
            padding: 8px 12px;
        }

        .proposal-card .q-card__actions {
            padding: 0 8px 4px;
        }

        /* Markdown styles inside assistant bubbles */
        .bubble pre {
            background: #263238;
            color: #eeffff;
            padding: 10px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 13px;
            margin: 8px 0;
        }

        .bubble code {
            font-family: 'Courier New', Courier, monospace;
        }

        .bubble :not(pre)>code {
            background: #e8e8e8;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 13px;
        }

        .bubble blockquote {
            border-left: 3px solid var(--q-primary);
            padding-left: 10px;
            margin: 8px 0;
            color: #666;
        }

        .bubble p {
            margin: 4px 0;
        }

        .bubble ul,
        .bubble ol {
            padding-left: 20px;
            margin: 4px 0;
        }

        /* Input bar */
        .input-bar {
            background: white;
            border-top: 1px solid #e0e0e0;
            padding: 12px 16px;
        }

        /* Loading indicator */
        .loading-indicator {
            float: left;
            color: #888;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .loading-indicator .dot {
            display: inline-block;
            animation: blink 1.4s infinite both;
        }

        .loading-indicator .dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .loading-indicator .dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes blink {

            0%,
            80%,
            100% {
                opacity: 0;
            }

            40% {
                opacity: 1;
            }
        }

        /* Clear floats */
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        /* Timestamp */
        /* Says "this line is about the exchange rather than part of it".
           A browser agent's question is marked because an unmarked bubble
           reads as something the user typed. */
        .external-note {
            float: left;
            clear: both;
            font-size: 11px;
            color: #888;
            margin: -8px 0 12px 4px;
        }

        .timestamp {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }

        .message.user .timestamp {
            text-align: right;
        }
    </style>
</head>

<body>
    <div id="q-app">
        <q-layout>
            <!-- Header -->
            <q-header class="ag-base-q-header">
                <q-toolbar>
                    <q-toolbar-title shrink>
                        <q-icon name="auto_awesome" size="md" class="q-mr-sm"></q-icon>
                        {{ pageTitle }}
                    </q-toolbar-title>
                    <q-space></q-space>
                </q-toolbar>
            </q-header>

            <q-page-container>
                <q-page>
                    <!-- Model selector bar -->
                    <div class="model-bar q-px-md q-py-sm row items-center">
                        <q-icon name="memory" size="sm" class="q-mr-sm text-grey-7"></q-icon>
                        <span class="text-grey-7 text-caption"><?php echo th('assistant.model'); ?></span>
                        <q-select
                            v-model="selectedModel"
                            :options="availableModels"
                            dense
                            outlined
                            class="q-ml-sm"
                            style="min-width: 180px;"
                            emit-value
                            map-options>
                        </q-select>
                    </div>

                    <!-- Chat area -->
                    <div class="chat-area" :class="{ 'chat-area--with-image': pendingImage }"
                        ref="chatContainer" style="overflow-y: auto; clear: both;">
                        <div class="clearfix">
                            <!-- Welcome message -->
                            <div v-if="messages.length === 0" class="text-center q-pa-xl text-grey-7">
                                <q-icon name="auto_awesome" size="xl" class="q-mb-md"></q-icon>
                                <div class="text-h6 q-mb-sm">{{ pageTitle }}</div>
                                <div><?php echo th('assistant.welcome'); ?></div>
                            </div>

                            <!-- Messages -->
                            <template v-for="(msg, index) in messages" :key="index">
                                <!-- Marked, because an unmarked bubble reads as
                                     something the user typed. -->
                                <div v-if="msg.external && msg.role === 'user'" class="external-note">
                                    <q-icon name="smart_toy" size="11px"></q-icon>
                                    <?php echo th('assistant.from_browser_agent'); ?>
                                </div>
                                <div
                                    class="message"
                                    :class="msg.role === 'user' ? 'user' : 'assistant'">
                                    <q-img v-if="msg.image" :src="msg.image" class="q-mb-xs rounded-borders"
                                        style="max-width: 180px;" fit="cover"></q-img>
                                    <div class="bubble" v-html="msg.role === 'assistant' ? renderMarkdown(msg.content) : escapeHtml(msg.content)"></div>
                                    <div class="timestamp">{{ formatTime(msg.timestamp) }}</div>
                                </div>
                            </template>

                            <!-- The draft the assistant proposed. Shown as its own
                                 card rather than a chat bubble: it is a reviewable
                                 object, and filling the form is a separate
                                 deliberate act from the draft arriving.

                                 One card serves both kinds of proposal. What differs
                                 between an operation and a crop is only which lines
                                 of the summary have anything in them, so branching
                                 on target here would duplicate the whole card to
                                 vary three rows. -->
                            <div v-if="proposal" class="proposal-card">
                                <q-card flat bordered
                                    :class="proposal.complete ? 'bg-blue-1' : 'bg-orange-1'">
                                    <q-card-section class="q-pb-none">
                                        <div class="row items-center q-gutter-xs">
                                            <q-icon :name="proposal.complete ? 'auto_awesome' : 'warning'" size="xs"></q-icon>
                                            <div class="text-weight-500">{{ proposal.title }}</div>
                                        </div>
                                    </q-card-section>
                                    <q-card-section class="text-body2">
                                        <div v-if="proposal.summary.headline">
                                            <strong>{{ proposal.summary.headline }}</strong>
                                            <span v-if="proposal.summary.date"> — {{ proposal.summary.date }}</span>
                                        </div>
                                        <div v-if="proposal.summary.where">
                                            {{ proposal.summary.where }}
                                        </div>
                                        <div v-for="(line, idx) in proposal.summary.lines" :key="idx">
                                            {{ line }}
                                        </div>
                                        <ul v-if="proposal.unresolved.length" class="q-mt-sm q-mb-none q-pl-md text-orange-10">
                                            <li v-for="(u, idx) in proposal.unresolved" :key="idx">{{ u }}</li>
                                        </ul>
                                    </q-card-section>
                                    <q-card-actions align="right">
                                        <!-- tv(), not tj(): tj() brings its own double
                                             quotes, which end the attribute and leave the
                                             button with no label at all. -->
                                        <q-btn flat dense :label="<?php echo tv('assistant.proposal_discard'); ?>"
                                            @click="proposal = null"></q-btn>
                                        <q-btn unelevated dense color="primary"
                                            :label="<?php echo tv('assistant.proposal_apply'); ?>"
                                            @click="applyProposalToForm"></q-btn>
                                    </q-card-actions>
                                </q-card>
                            </div>

                            <!-- Loading indicator -->
                            <div v-if="loading" class="loading-indicator">
                                <q-icon name="blur_on" size="sm" class="q-mr-xs"></q-icon>
                                <span class="dot">●</span><span class="dot">●</span><span class="dot">●</span>
                            </div>
                        </div>
                    </div>

                    <!-- Input bar -->
                    <div class="input-bar">
                        <!-- The picture waiting to be sent. Shown above the box so
                             it is obvious it will go with the next message, and
                             removable, because attaching the wrong photograph is
                             the easiest mistake to make here. -->
                        <div v-if="pendingImage" class="row items-center q-gutter-sm q-mb-sm">
                            <q-img :src="pendingImage" style="width: 48px; height: 48px;"
                                class="rounded-borders" fit="cover"></q-img>
                            <div class="text-caption text-grey-7 col ellipsis">{{ pendingImageName }}</div>
                            <q-btn flat dense round size="sm" icon="close" @click="clearPendingImage"></q-btn>
                        </div>

                        <div class="row q-gutter-sm items-end">
                            <!-- keydown .exact: Enter sends, Shift+Enter breaks the line. -->
                            <q-input
                                v-model="inputMessage"
                                type="textarea"
                                :input-style="{ height: '63px', resize: 'none' }"
                                dense
                                outlined
                                placeholder="<?php echo th('assistant.input_placeholder'); ?>"
                                class="col"
                                @keydown.enter.exact.prevent="sendMessage"
                                @paste="onPaste"
                                :disable="loading">
                                <template v-slot:append>
                                    <q-icon name="send" class="cursor-pointer" @click="sendMessage" :class="{ 'text-primary': !loading }"></q-icon>
                                </template>
                            </q-input>
                            <q-btn dense outline round icon="add_a_photo" color="primary"
                                :disable="loading"
                                @click="$refs.imageInput.click()">
                                <q-tooltip><?php echo th('assistant.attach_image'); ?></q-tooltip>
                            </q-btn>
                            <!-- capture= asks a phone for the camera directly, which
                                 is where a scouting photograph is actually taken. -->
                            <input ref="imageInput" type="file" accept="image/*" capture="environment"
                                style="display: none;" @change="onImagePicked">
                        </div>
                    </div>
                </q-page>
            </q-page-container>
        </q-layout>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@15.0.12/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>

    <script>
        const API_URL = <?php echo json_encode($agtApiUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;
        // The agent layer, beside this page: same ?action= call shape as API_URL.
        // Relative, so /agt-pt/chat.php reaches /agt-pt/assistant.php without a
        // second address to configure.
        const AGT_URL = <?php echo json_encode($agtEndpoint, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;
        const AGT_LOGIN_URL = <?php echo json_encode($agtAppUrl . '/login.php', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;

        const app = Vue.createApp({
            data() {
                return {
                    pageTitle: '<?php echo $pageTitle; ?>',
                    krdValue: '',
                    messages: [],
                    inputMessage: '',
                    loading: false,
                    // Validated draft awaiting the user's decision. Held here
                    // rather than pushed into messages[] because it is an object to
                    // act on, not conversation to replay as context.
                    proposal: null,
                    // The photograph queued for the next message, already downscaled.
                    pendingImage: '',
                    pendingImageName: '',
                    // Every photograph sent this session, in order. The model refers
                    // to them by position ("image 2"), because a number is the only
                    // handle it can hold: the bytes stay in this browser and never
                    // come back from the server. This is what turns that number back
                    // into a picture when a draft is applied.
                    attachments: [],
                    // Placeholder only, shown for the moment before
                    // fetchStatus() replaces it with the real list. The
                    // authoritative list comes from the configured opx
                    // instances plus Gemini; see
                    // agt/config-ai-openai-compatible.php.
                    selectedModel: '',
                    availableModels: []
                };
            },
            mounted() {
                // No hard auth gate here: the server enforces access; a 401
                // from fetchStatus()/sendMessage() is what redirects to login.

                // A scouting entry names a picture by NUMBER, because the bytes
                // never leave this browser. This pane swaps them itself before
                // calling mskProposeFill; a proposal arriving from the WebMCP
                // registrar instead comes from outside the frame, so the shell
                // footer does the swap by calling this. Same function either way.
                window.mskResolveScoutingImages = payload => this.withAttachedImages(payload);

                // What agt/webmcp-ask.js delivers a browser agent's exchange to.
                this.publishExchangeSink();

                // Resolve krd (tenant database) from URL param or parent window (same as fields.php)
                const urlParams = new URLSearchParams(window.location.search);
                this.krdValue = urlParams.get('krd') || window.parent.krd || '';
                if (!this.krdValue) {
                    console.warn(<?php echo tj('assistant.krd_warning'); ?>);
                }

                // Restore chat history from parent window (persists across iframe navigation)
                this.messages = window.parent.assistantChatHistory || [];

                // Restore the previously selected model (survives the iframe reload
                // the shell does when a database is picked). Must run before
                // fetchStatus(), which only overrides it if it isn't available.
                if (window.top.assistantSelectedModel) {
                    this.selectedModel = window.top.assistantSelectedModel;
                }

                // Fetch available models
                this.fetchStatus();

                // Scroll to bottom after mount
                this.$nextTick(() => {
                    this.scrollToBottom();
                });
            },
            watch: {
                // Publish the chosen model to the shell so it survives the iframe
                // reload the shell does when a database is picked - the mount above
                // reads it back. This pane is the only thing that calls a model,
                // so the choice made here is the only one there is.
                //
                // Published on window.top, not window.parent: this pane is a frame
                // of the shell, and window.top is the shell at every frame depth.
                //
                // immediate: true because a Vue watcher does not fire for the
                // initial value, and fetchStatus() only reassigns selectedModel
                // when the current one is unavailable — so the default model would
                // otherwise never be published at all.
                selectedModel: {
                    immediate: true,
                    handler(newModel) {
                        if (newModel) {
                            window.top.assistantSelectedModel = newModel;
                        }
                    }
                }
            },
            methods: {
                // The transcript in the shape the API takes it.
                //
                // Images are sent only for the last two turns that carry one. The
                // server keeps twelve turns, and a photograph is up to 8 MB of
                // base64: resending every one on every message would grow the
                // request until it timed out, for no benefit, since by then the
                // assistant's own reading of the older pictures is in the
                // transcript as text.
                chatHistory() {
                    // DISPLAY-ONLY turns are dropped here, and this filter is the
                    // whole of what "display-only" means. A browser agent's
                    // question and its answer are shown so the user can see what
                    // was asked of their farm, but that exchange ran its own loop
                    // with its own tool calls, none of which are in this
                    // transcript. Feeding the pair back would give this model an
                    // answer it cannot account for.
                    //
                    // Filtered BEFORE the image indices are taken, so the two
                    // stay in step - recentImages indexes the array this returns.
                    const own = this.messages.filter(m => !m.external);

                    const recentImages = own
                        .map((m, i) => (m.image ? i : -1))
                        .filter(i => i >= 0)
                        .slice(-2);

                    return own.map((m, i) => {
                        const turn = { role: m.role, content: m.content };
                        if (m.image && recentImages.includes(i)) turn.image = m.image;
                        return turn;
                    });
                },

                // Reads a picked or pasted file and queues it for the next message.
                // Downscaled first: a phone photograph is several megabytes, the
                // server refuses anything over eight, and no amount of detail past
                // 1280px helps a model identify a weed.
                async attachImageFile(file) {
                    if (!(file instanceof File) || !file.type.startsWith('image/')) return;

                    try {
                        const dataUrl = await this.readBlobAsDataUrl(file);
                        this.pendingImage = await this.downscaleImageDataUrl(dataUrl);
                        this.pendingImageName = file.name || '';
                    } catch (e) {
                        console.error('Attachment failed:', e);
                        this.pendingImage = '';
                        this.pendingImageName = '';
                    }
                },

                onImagePicked(event) {
                    const file = event.target.files && event.target.files[0];
                    // Cleared so picking the same file twice in a row still fires
                    // a change event.
                    event.target.value = '';
                    this.attachImageFile(file);
                },

                onPaste(event) {
                    const items = (event.clipboardData && event.clipboardData.items) || [];
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type && items[i].type.startsWith('image/')) {
                            event.preventDefault();
                            this.attachImageFile(items[i].getAsFile());
                            return;
                        }
                    }
                },

                clearPendingImage() {
                    this.pendingImage = '';
                    this.pendingImageName = '';
                },

                readBlobAsDataUrl(blob) {
                    return new Promise((resolve, reject) => {
                        const r = new FileReader();
                        r.onload = () => resolve(r.result);
                        r.onerror = reject;
                        r.readAsDataURL(blob);
                    });
                },

                async downscaleImageDataUrl(dataUrl, maxDimension = 1280, quality = 0.85) {
                    try {
                        const image = await new Promise((resolve, reject) => {
                            const img = new Image();
                            img.onload = () => resolve(img);
                            img.onerror = reject;
                            img.src = dataUrl;
                        });
                        const scale = Math.min(1, maxDimension / Math.max(image.width, image.height));
                        if (scale >= 1) return dataUrl;
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.round(image.width * scale);
                        canvas.height = Math.round(image.height * scale);
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
                        return canvas.toDataURL('image/jpeg', quality);
                    } catch (e) {
                        return dataUrl;
                    }
                },

                async fetchStatus() {
                    try {
                        const token = localStorage.getItem('auth_token');
                        const headers = {};
                        if (token) headers['Authorization'] = 'Bearer ' + token;
                        const url = AGT_URL + '?action=status' +
                            (this.krdValue ? '&krd=' + encodeURIComponent(this.krdValue) : '');
                        const res = await fetch(url, {
                            headers
                        });
                        if (res.status === 401) {
                            localStorage.removeItem('auth_token');
                            window.location.href = AGT_LOGIN_URL;
                            return;
                        }
                        if (!res.ok) throw new Error('Auth failed');
                        const payload = await res.json();
                        const data = payload.data || {};
                        if (data.available_models && data.available_models.length > 0) {
                            // Label and value differ on purpose. The value must
                            // keep its provider prefix - resolveProviderAndModel()
                            // splits on the first colon to decide between gemini
                            // and opx, and "gemini-3.6-flash" on its own has no
                            // colon at all, so it would fall through to opx and be
                            // sent to the Ollama box.
                            //
                            // Only a known provider prefix is stripped, never the
                            // whole leading segment: an Ollama name carries its own
                            // colon ("qwen3.8:27b"), and cutting at that would show
                            // every local model as its parameter count.
                            this.availableModels = data.available_models.map(m => ({
                                label: m.startsWith('gemini:') ? m.slice('gemini:'.length) : m,
                                value: m
                            }));
                            if (!data.available_models.includes(this.selectedModel)) {
                                this.selectedModel = data.model && data.available_models.includes(data.model) ?
                                    data.model :
                                    data.available_models[0];
                            }
                        }
                    } catch (e) {
                        console.error('Status error:', e);
                    }
                },

                // Turns the draft the server validated into the card's contents.
                // Every id in it has already been checked against the tenant
                // database by validateOperationFill() / validateCropFill(), so
                // there is nothing to verify here - only to word.
                buildProposal(proposal) {
                    const draft = proposal.payload || {};
                    const common = {
                        target: proposal.target,
                        payload: draft,
                        complete: draft.complete === true,
                        unresolved: Array.isArray(draft.unresolved) ? draft.unresolved : []
                    };

                    if (proposal.target === 'crops') {
                        return Object.assign(common, {
                            title: <?php echo tj('assistant.proposal_title_crop'); ?>,
                            summary: {
                                headline: draft.resolved?.production_name || '',
                                date: draft.dti || '',
                                where: draft.resolved?.field_name || '',
                                lines: []
                            }
                        });
                    }

                    return Object.assign(common, {
                        title: <?php echo tj('assistant.proposal_title'); ?>,
                        summary: {
                            headline: draft.resolved?.operation_type_name || '',
                            date: draft.operation_date || '',
                            where: (draft.resolved?.crops || [])
                                .map(fc => fc.field_name + ' / ' + fc.production_name)
                                .join(', '),
                            lines: (draft.inputs || []).map(i => {
                                // A rate the user never stated comes back null, and
                                // plain concatenation would print the word "null"
                                // at them. Name the gap instead — the unresolved
                                // list below says what to do about it.
                                const rate = i.application_rate_per_ha;
                                return rate === null || rate === undefined || rate === ''
                                    ? i.name + ' — ' + <?php echo tj('assistant.proposal_no_rate'); ?> + ' (' + i.unit + '/ha)'
                                    : i.name + ' — ' + rate + ' ' + i.unit + '/ha';
                            }).concat(
                                (draft.scouting_images || []).map(image => <?php echo tj('assistant.proposal_image'); ?> + ' ' + image.image + ': ' + image.note)
                            )
                        }
                    });
                },

                // Hands the draft to the form through the shell. The assistant never
                // writes: the form owns the save, so the user sees the values before
                // anything reaches the database.
                applyProposalToForm() {
                    if (!this.proposal) return;

                    if (window.top === window || typeof window.top.mskProposeFill !== 'function') {
                        this.messages.push({
                            role: 'assistant',
                            content: <?php echo tj('assistant.proposal_no_shell'); ?>,
                            timestamp: Date.now()
                        });
                        return;
                    }

                    window.top.mskProposeFill(this.proposal.target, this.withAttachedImages(this.proposal.payload));
                    this.proposal = null;
                },

                // Swaps each image NUMBER the model used for the picture itself,
                // which only this page has. An entry naming an image that was never
                // sent is dropped rather than carried as a broken row: the model
                // counted wrong, and there is nothing to attach.
                withAttachedImages(payload) {
                    if (!Array.isArray(payload.scouting_images) || payload.scouting_images.length === 0) {
                        return payload;
                    }

                    const resolved = [];
                    payload.scouting_images.forEach(entry => {
                        const attachment = this.attachments[entry.image - 1];
                        if (!attachment) {
                            console.warn('Proposal referred to image ' + entry.image + ', which was never attached.');
                            return;
                        }
                        resolved.push({
                            data_url: attachment.dataUrl,
                            file_name: attachment.name,
                            note: entry.note
                        });
                    });

                    return Object.assign({}, payload, { scouting_images: resolved });
                },

                async sendMessage() {
                    // A caption stands in when the user attaches a photograph and
                    // sends it with no words. The server drops any turn whose text
                    // is empty, so an image on its own would vanish silently, and
                    // "look at this" is what they meant anyway.
                    const text = this.inputMessage.trim()
                        || (this.pendingImage ? <?php echo tj('assistant.image_default_caption'); ?> : '');
                    if (!text || this.loading) return;

                    const token = localStorage.getItem('auth_token');
                    const userMsg = {
                        role: 'user',
                        content: text,
                        timestamp: Date.now()
                    };

                    // Numbered from 1 in send order, which is exactly how the model
                    // is told to refer to them, so the index it names indexes this.
                    if (this.pendingImage) {
                        userMsg.image = this.pendingImage;
                        this.attachments.push({ dataUrl: this.pendingImage, name: this.pendingImageName });
                        this.clearPendingImage();
                    }

                    this.messages.push(userMsg);
                    this.inputMessage = '';
                    this.loading = true;
                    // A new turn supersedes whatever was proposed on the last one.
                    this.proposal = null;

                    // Scroll to show user message
                    this.$nextTick(() => this.scrollToBottom());
                    this.syncChatToParent();

                    const messageHistory = this.chatHistory();

                    try {
                        const url = AGT_URL + '?action=chat' +
                            (this.krdValue ? '&krd=' + encodeURIComponent(this.krdValue) : '');
                        const headers = {
                            'Content-Type': 'application/json'
                        };
                        if (token) headers['Authorization'] = 'Bearer ' + token;
                        const res = await fetch(url, {
                            method: 'POST',
                            headers,
                            body: JSON.stringify({
                                messages: messageHistory,
                                model: this.selectedModel
                            })
                        });

                        if (!res.ok) {
                            if (res.status === 401) {
                                localStorage.removeItem('auth_token');
                                window.location.href = AGT_LOGIN_URL;
                                return;
                            }
                            let detail = '';
                            try {
                                const errBody = await res.json();
                                detail = errBody.message || errBody.detail || errBody.error || '';
                            } catch (parseErr) {
                                /* ignore */ }
                            throw new Error('API error: ' + res.status + (detail ? ' (' + detail + ')' : ''));
                        }

                        const payload = await res.json();
                        if (!payload.success) throw new Error(payload.message || <?php echo tj('assistant.ai_error'); ?>);

                        this.messages.push({
                            role: 'assistant',
                            content: payload.data?.content || '',
                            timestamp: Date.now()
                        });

                        // A draft the assistant decided to propose this turn. It
                        // arrives beside the reply rather than inside it, so the
                        // model's account of what it proposed and the reviewable
                        // object itself cannot drift apart.
                        if (payload.data?.proposal) {
                            this.proposal = this.buildProposal(payload.data.proposal);
                        }

                        this.syncChatToParent();
                    } catch (e) {
                        console.error('Chat error:', e);
                        this.messages.push({
                            role: 'assistant',
                            content: <?php echo tj('assistant.ai_error'); ?> + ': ' + e.message,
                            timestamp: Date.now()
                        });
                    } finally {
                        this.loading = false;
                        this.$nextTick(() => this.scrollToBottom());
                    }
                },

                scrollToBottom() {
                    const el = this.$refs.chatContainer;
                    if (el) el.scrollTop = el.scrollHeight;
                },

                escapeHtml(text) {
                    return text
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                },

                renderMarkdown(text) {
                    if (!text) return '';
                    try {
                        const rawHtml = marked.parse(text);
                        const cleanHtml = DOMPurify.sanitize(rawHtml);
                        return this.renderMathToString(cleanHtml);
                    } catch (e) {
                        return this.escapeHtml(text);
                    }
                },

                // Renders $...$/$$...$$ math inside a detached scratch element — never
                // the live chat DOM. An earlier version ran renderMathInElement directly
                // on $refs.chatContainer after each message; KaTeX's in-place DOM
                // surgery (replacing text nodes with its own markup) invalidated the
                // node references Vue's patcher relies on, and the next messages.push()
                // crashed with "insertBefore ... not a child of this node". Rendering
                // into a detached node and returning its innerHTML keeps this a plain
                // string transform, like marked/DOMPurify above, so Vue only ever diffs
                // a v-html string it fully controls.
                renderMathToString(html) {
                    if (typeof renderMathInElement !== 'function') return html;
                    const scratch = document.createElement('div');
                    scratch.innerHTML = html;
                    try {
                        renderMathInElement(scratch, {
                            delimiters: [{
                                    left: '$$',
                                    right: '$$',
                                    display: true
                                },
                                {
                                    left: '\\[',
                                    right: '\\]',
                                    display: true
                                },
                                {
                                    left: '$',
                                    right: '$',
                                    display: false
                                },
                                {
                                    left: '\\(',
                                    right: '\\)',
                                    display: false
                                }
                            ],
                            throwOnError: false
                        });
                    } catch (e) {
                        return html;
                    }
                    return scratch.innerHTML;
                },

                formatTime(timestamp) {
                    return new Date(timestamp).toLocaleTimeString('pt-BR', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },

                // Persists the transcript across the iframe reload the shell does
                // when a database is picked.
                //
                // Images are stripped. This global lives for as long as the tab
                // does, and parking several megabytes of base64 on it per
                // photograph would grow without bound, for a thumbnail nobody
                // looks at twice. The caption stays, so the transcript still reads
                // correctly; the picture is gone, which also means an attachment
                // number cannot be resolved after a reload - withAttachedImages()
                // drops those rather than attaching the wrong photograph.
                // Where a browser agent's exchange lands in this transcript.
                // external: true keeps it display-only - chatHistory() drops
                // those turns, and syncChatToParent() spreads ...rest so the
                // flag survives a frame reload.
                publishExchangeSink() {
                    window.agtAppendExchange = (question, answer) => {
                        const q = String(question == null ? '' : question).trim();
                        const a = String(answer == null ? '' : answer).trim();
                        if (!q || !a) return false;

                        const at = Date.now();
                        this.messages.push({ role: 'user', content: q, timestamp: at, external: true });
                        this.messages.push({ role: 'assistant', content: a, timestamp: at, external: true });

                        this.$nextTick(() => this.scrollToBottom());
                        this.syncChatToParent();
                        return true;
                    };
                },

                syncChatToParent() {
                    if (typeof window.parent !== 'undefined') {
                        window.parent.assistantChatHistory = this.messages.map(m => {
                            const { image, ...rest } = m;
                            return rest;
                        });
                    }
                },

            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>

</html>