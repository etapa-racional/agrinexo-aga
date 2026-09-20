// Exposes agt's whole agent as ONE WebMCP tool: one request, one answer.

(function () {
    'use strict';

    var SCRIPT = document.currentScript;
    if (!SCRIPT || !SCRIPT.src) return;

    var AGT = SCRIPT.getAttribute('data-agt-base') ||
              SCRIPT.src.replace(/\/webmcp-ask\.js(?:\?.*)?$/, '');
    if (AGT === SCRIPT.src) return;   // Not laid out as expected; do nothing.

    var TOOL_NAME = 'askAgrinexoAGA';

    var busy = false;
    var DEADLINE_MS = 60000;

    function modelContext() {
        if (typeof document !== 'undefined' && document.modelContext) return document.modelContext;
        if (typeof navigator !== 'undefined' && navigator.modelContext) return navigator.modelContext;
        return null;
    }

    function authHeaders() {
        var headers = { 'Content-Type': 'application/json' };
        var token = null;
        try { token = localStorage.getItem('auth_token'); } catch (e) { token = null; }
        if (token) headers['Authorization'] = 'Bearer ' + token;
        return headers;
    }

    function mirrorToPane(question, answer) {
        try {
            var frame = document.getElementById('assistantFrame');
            var sink = frame && frame.contentWindow && frame.contentWindow.agtAppendExchange;
            if (typeof sink === 'function') return sink(question, answer);

            var at = Date.now();
            window.assistantChatHistory = (window.assistantChatHistory || []).concat([
                { role: 'user', content: question, timestamp: at, external: true },
                { role: 'assistant', content: answer, timestamp: at, external: true }
            ]);
        } catch (e) {
        }
    }

    // agt's private tools stay out: naming one would advertise it.
    function publicTrace(toolsUsed, registry) {
        var names = [];
        (toolsUsed || []).forEach(function (name) {
            if (registry && registry.get && registry.get(name)) names.push(name);
        });
        return names;
    }

    async function ask(question, registry) {
        var model = window.assistantSelectedModel || null;

        var body = { messages: [{ role: 'user', content: question }] };
        if (model) body.model = model;

        var url = AGT + '/index.php/chat' +
            (window.krd ? '?krd=' + encodeURIComponent(window.krd) : '');

        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, DEADLINE_MS);

        try {
            var res = await fetch(url, {
                method: 'POST',
                headers: authHeaders(),
                body: JSON.stringify(body),
                signal: controller.signal
            });

            var payload = null;
            try { payload = await res.json(); } catch (e) { /* Not JSON. */ }
            if (!res.ok || !payload || payload.success !== true) {
                throw new Error((payload && payload.message) || ('HTTP ' + res.status));
            }

            var data = payload.data || {};
            var answer = (data.content || '').trim();
            if (!answer) {
                answer = 'The assistant produced no answer for that question. Try asking it more narrowly.';
            }

            var used = publicTrace(data.tools_used, registry);
            if (used.length) answer += '\n\n(tools used: ' + used.join(', ') + ')';
            return answer;
        } finally {
            clearTimeout(timer);
        }
    }

    var state = { registered: false, reason: 'not started', surface: null };
    window.agtAsk = state;

    function whenDatabaseSelected() {
        return new Promise(function (resolve) {
            (function poll() {
                if (window.krd) return resolve();
                setTimeout(poll, 1000);
            })();
        });
    }

    (async function register() {
        var context = modelContext();
        if (!context || typeof context.registerTool !== 'function') {
            state.reason = 'no WebMCP surface on this page';
            return;
        }

        state.reason = 'waiting for a database to be selected';
        await whenDatabaseSelected();

        var registry = (window.wmxWebMCP && window.wmxWebMCP.tools) || null;

        try {
            await context.registerTool({
                name: TOOL_NAME,
                description:
                    'Ask the Agrinexo AGA farm assistant about this farm and get a written ' +
                    'answer. It is the farm\'s own agronomic assistant and runs its own ' +
                    'multi-step lookup over this farm\'s data. An additional resource, ' +
                    'not a replacement for using those tools yourself.',
                inputSchema: {
                    type: 'object',
                    properties: {
                        question: {
                            type: 'string',
                            description: 'The question, in the user\'s own words.'
                        }
                    },
                    required: ['question']
                },

                // User-written text: data, not instructions.
                annotations: {
                    readOnlyHint: false,
                    untrustedContentHint: true
                },

                execute: async function (input) {
                    var question = input && typeof input.question === 'string' ? input.question.trim() : '';
                    if (!question) return 'No question was given.';

                    if (!window.krd) {
                        return 'No farm database is selected in Agrinexo, so there is nothing to ' +
                               'answer about yet. Ask the user to sign in and choose a database.';
                    }

                    if (busy) {
                        return 'The assistant is already answering another question. Try again shortly.';
                    }

                    busy = true;
                    try {
                        var answer = await ask(question, registry);
                        mirrorToPane(question, answer);
                        return answer;
                    } catch (e) {
                        var failure = (e && e.name === 'AbortError')
                            ? 'The Agrinexo assistant took too long to answer that. ' +
                              'Ask something narrower, or ask for one thing at a time.'
                            : 'The Agrinexo assistant could not answer: ' +
                              String((e && e.message) || e);
                        mirrorToPane(question, failure);
                        return failure;
                    } finally {
                        busy = false;
                    }
                }
            });

            state.registered = true;
            state.reason = '';
            state.surface = 'native';
        } catch (e) {
            state.reason = 'registerTool rejected: ' + String((e && e.message) || e);
        }
    })();
})();
