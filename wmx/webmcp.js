// ONE RULE: only READ from the shell - never wrap, patch or replace it.

(function () {
    'use strict';

    var SCRIPT = document.currentScript;
    if (!SCRIPT || !SCRIPT.src) return;

    var BASE = SCRIPT.getAttribute('data-wmx-base') ||
               SCRIPT.src.replace(/\/webmcp\.js(?:\?.*)?$/, '');

    var POLL_MS = 3000;
    var registered = null;   // AbortController for the current registration
    var registeredKrd = null;

    var registry = new Map();
    var offered = 0;      // how many the backend published, vs how many registered
    var registering = false;   // a registration is in flight; see sync()

    var failed = [];

    var readyResolve;
    var ready = new Promise(function (resolve) { readyResolve = resolve; });

    // The accessor moved Navigator->Document; resolved in one place.
    function getModelContext() {
        if (typeof document !== 'undefined' && document.modelContext) return document.modelContext;
        if (typeof navigator !== 'undefined' && navigator.modelContext) return navigator.modelContext;
        return null;
    }

    function available() {
        return !!(window.isSecureContext && getModelContext());
    }

    function authHeaders() {
        var headers = { 'Content-Type': 'application/json' };
        var token = null;
        try {
            token = localStorage.getItem('auth_token');
        } catch (e) {
        }
        if (token) headers['Authorization'] = 'Bearer ' + token;
        return headers;
    }

    function toolUrl(route, name, krd) {
        return BASE + '/index.php/' + route
            + '?krd=' + encodeURIComponent(krd)
            + (name ? '&name=' + encodeURIComponent(name) : '');
    }

    // Chrome requires execute() to resolve to a STRING, not an object.
    function asText(value) {
        return typeof value === 'string' ? value : JSON.stringify(value);
    }

    // The propose tools do not write: the user reviews and saves.
    function proposeBridge() {
        try {
            if (window.top && typeof window.top.mskProposeFill === 'function') {
                return function (target, payload) { return window.top.mskProposeFill(target, payload); };
            }
        } catch (e) {
        }
        if (typeof window.mskProposeFill === 'function') {
            return function (target, payload) { return window.mskProposeFill(target, payload); };
        }
        return null;
    }

    function deliverProposal(toolName, result) {
        var target = toolName === 'propose_crop' ? 'crops' : 'operations';
        var bridge = proposeBridge();

        if (!bridge) {
            return asText({
                delivered: false,
                reason: 'The Agrinexo shell is not present, so the form cannot be filled.',
                draft: result
            });
        }

        var outcome = bridge(target, result);

        if (outcome === 'unroutable') {
            return asText({
                delivered: false,
                reason: 'The draft names no field that exists on this farm, so the form could not be opened. Resolve the field with list_fields and propose again.',
                unresolved: result && result.unresolved,
                draft: result
            });
        }

        return asText({
            delivered: true,
            outcome: outcome,               // 'delivered' | 'navigating' | 'offline'
            awaiting_user: true,
            note: outcome === 'navigating'
                ? 'Accepted. The app is opening the ' + target + ' form for the user; the draft will be waiting there. Nothing is saved until they review it and press Save.'
                : 'The draft is on screen in the ' + target + ' form. Nothing is saved until the user reviews it and presses Save.',
            unresolved: result && result.unresolved,
            complete: result && result.complete
        });
    }

    function normaliseSchema(schema) {
        if (!schema || typeof schema !== 'object') return { type: 'object' };

        var properties = schema.properties;
        var hasProperties = properties && typeof properties === 'object' && Object.keys(properties).length > 0;
        if (hasProperties) return schema;

        return { type: schema.type || 'object' };
    }

    function makeTool(definition, krd) {
        return {
            name: definition.name,
            description: definition.description,
            inputSchema: normaliseSchema(definition.input_schema),

            annotations: {
                readOnlyHint: !!definition.read_only,
                untrustedContentHint: true
            },

            execute: async function (input) {
                var response, payload;

                try {
                    response = await fetch(toolUrl('tool', definition.name, krd), {
                        method: 'POST',
                        headers: authHeaders(),
                        body: JSON.stringify(input || {})
                    });
                    payload = await response.json();
                } catch (e) {
                    return asText({ error: 'Could not reach Agrinexo: ' + e.message });
                }

                if (!payload || payload.success !== true) {
                    return asText({ error: (payload && payload.message) || ('HTTP ' + response.status) });
                }

                var result = payload.data && payload.data.result;

                if (definition.name.indexOf('propose_') === 0) {
                    return deliverProposal(definition.name, result);
                }

                return asText(result);
            }
        };
    }

    async function register(krd) {
        var context = getModelContext();
        if (!context) {
            readyResolve(registry);
            return;
        }

        var definitions;
        try {
            var response = await fetch(toolUrl('tools', null, krd), { headers: authHeaders() });
            var payload = await response.json();
            if (!payload || payload.success !== true) {
                readyResolve(registry);
                return;
            }
            definitions = payload.data.tools || [];
        } catch (e) {
            readyResolve(registry);
            return;   // Backend unreachable; try again on the next krd change.
        }

        var controller = new AbortController();
        registry.clear();
        failed.length = 0;

        for (var i = 0; i < definitions.length; i++) {
            var tool = makeTool(definitions[i], krd);
            try {
                await context.registerTool(tool, { signal: controller.signal });
                registry.set(tool.name, tool);
            } catch (e) {
                failed.push({ name: definitions[i].name, reason: String((e && e.message) || e) });
                console.warn('[wmx] could not register ' + definitions[i].name + ':', e);
            }
        }

        offered = definitions.length;

        registered = controller;
        registeredKrd = krd;
        readyResolve(registry);
    }

    function unregister() {
        if (registered) registered.abort();
        registered = null;
        registeredKrd = null;
        registry.clear();
        failed.length = 0;
        offered = 0;
    }

    function sync() {
        if (!available() || registering) return;

        var krd = window.krd || null;
        if (krd === registeredKrd) return;

        registering = true;
        unregister();

        if (!krd) {
            registering = false;
            return;
        }

        var settle = function () { registering = false; };
        register(krd).then(settle, settle);
    }

    window.wmxWebMCP = {
        available: available,
        getModelContext: getModelContext,
        tools: registry,
        failed: failed,
        offered: function () { return offered; },
        ready: ready,
        base: BASE
    };

    if (!available()) {
        readyResolve(registry);   // empty: nothing registered, and the host must be able to tell
        return;
    }

    sync();
    setInterval(sync, POLL_MS);
    window.addEventListener('focus', sync);
})();
