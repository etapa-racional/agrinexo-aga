// Gives the page a WebMCP registration surface, then loads webmcp.js.

(function () {
    'use strict';

    var SCRIPT = document.currentScript;
    if (!SCRIPT || !SCRIPT.src) return;

    var WMX = SCRIPT.src.replace(/\/aibridge\.js(?:\?.*)?$/, '/webmcp.js');
    if (WMX === SCRIPT.src) return;   // Not laid out as expected; do nothing.

    var BASE = SCRIPT.getAttribute('data-wmx-base') || '';

    var FORWARD = SCRIPT.getAttribute('data-wmx-forward') !== '0';

    function hasNative() {
        return !!((typeof document !== 'undefined' && document.modelContext) ||
                  (typeof navigator !== 'undefined' && navigator.modelContext));
    }


    var registry = new Map();

    var native = !FORWARD ? null :
                 ((typeof document !== 'undefined' && document.modelContext) ||
                  (typeof navigator !== 'undefined' && navigator.modelContext) ||
                  null);

    function forward(method, args, name) {
        if (!native || typeof native[method] !== 'function') return;
        try {
            var out = native[method].apply(native, args);
            if (out && typeof out.catch === 'function') {
                out.catch(function (e) {
                    state.nativeFailed.push({ name: name, reason: String((e && e.message) || e) });
                });
            }
        } catch (e) {
            state.nativeFailed.push({ name: name, reason: String((e && e.message) || e) });
        }
    }

    var bridge = {
        registerTool: function (tool, options) {
            try {
                if (!tool || typeof tool !== 'object') {
                    throw new TypeError('registerTool(tool) requires a tool object');
                }
                if (typeof tool.name !== 'string' || tool.name === '') {
                    throw new TypeError('Tool "name" is required');
                }
                if (typeof tool.execute !== 'function') {
                    throw new TypeError('Tool "execute" must be a function');
                }
                if (registry.has(tool.name)) {
                    throw new Error('Tool already registered: ' + tool.name);
                }

                registry.set(tool.name, tool);

                var signal = options && options.signal;
                if (signal) {
                    if (signal.aborted) {
                        registry.delete(tool.name);
                        return Promise.reject(signal.reason);
                    }
                    signal.addEventListener('abort', function () {
                        registry.delete(tool.name);
                    }, { once: true });
                }

                forward('registerTool', [tool, options], tool.name);

                return Promise.resolve();
            } catch (e) {
                return Promise.reject(e);
            }
        },

        unregisterTool: function (name) {
            registry.delete(name);
            forward('unregisterTool', [name], name);
            return Promise.resolve();
        }
    };

    var state = {
        native: hasNative(),        // did this browser implement WebMCP itself
        forwarding: FORWARD,        // are registrations passed on to it
        installed: false,           // is the surface below the one in use
        secureContext: !!window.isSecureContext,
        tools: function () { return registry.size; },
        error: null,
        nativeFailed: []
    };
    window.wmxShim = state;

    try {
        Object.defineProperty(document, 'modelContext', { value: bridge, configurable: true });
        Object.defineProperty(navigator, 'modelContext', { value: bridge, configurable: true });
        state.installed = document.modelContext === bridge;
    } catch (e) {
        state.error = 'Could not install the WebMCP surface: ' + e.message;
    }

    // Injected, not tagged: the surface must exist before wmx's gate runs.
    var tag = document.createElement('script');
    tag.src = WMX;
    if (BASE !== '') tag.setAttribute('data-wmx-base', BASE);
    tag.async = false;
    document.head.appendChild(tag);
})();
