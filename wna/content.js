// Isolated world sees native WebMCP bindings, not a page's own shim.

(function () {
    'use strict';

    var byName = new Map();

    var TOOL_TIMEOUT_MS = 30000;

    var listening = false;

    function surface() {
        return (typeof document !== 'undefined' && document.modelContext) || null;
    }

    // inputSchema arrives and arguments leave as JSON STRINGs.
    function asSchema(schema) {
        if (schema && typeof schema === 'object') return schema;
        if (typeof schema === 'string' && schema.trim() !== '') {
            try { return JSON.parse(schema); } catch (e) { /* fall through */ }
        }
        return { type: 'object', properties: {} };
    }

    function serialisable(tool) {
        return {
            name: tool.name,
            description: tool.description || '',
            inputSchema: asSchema(tool.inputSchema),
            annotations: tool.annotations || null,
            origin: typeof tool.origin === 'string' ? tool.origin : null
        };
    }

    async function discover() {
        var context = surface();
        if (!context || typeof context.getTools !== 'function') {
            return report([], 'no native WebMCP surface on this page (is the flag or origin trial on?)');
        }

        var tools;
        try {
            tools = await context.getTools();
        } catch (e) {
            return report([], 'getTools() rejected: ' + String((e && e.message) || e));
        }

        byName.clear();
        (tools || []).forEach(function (tool) {
            if (tool && tool.name) byName.set(tool.name, tool);
        });

        if (!listening && typeof context.addEventListener === 'function') {
            context.addEventListener('toolchange', function () { discover(); });
            listening = true;
        }

        report((tools || []).map(serialisable), null);
    }

    function report(tools, reason) {
        try {
            chrome.runtime.sendMessage({ type: 'TOOLS_DISCOVERED', data: { tools: tools, reason: reason } })
                .catch(function () { /* No panel open. */ });
        } catch (e) {
        }
    }

    function flatten(result) {
        if (result == null) return '';
        if (typeof result === 'string') return result;

        var parts = result.content;
        if (Array.isArray(parts)) {
            var text = parts
                .filter(function (p) { return p && typeof p.text === 'string'; })
                .map(function (p) { return p.text; })
                .join('\n');
            if (text) return text;
        }
        try { return JSON.stringify(result); } catch (e) { return String(result); }
    }

    async function executeTool(name, args) {
        var context = surface();
        var tool = byName.get(name);
        if (!context || !tool) throw new Error('Unknown tool on this page: ' + name);

        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, TOOL_TIMEOUT_MS);
        try {
            var result = await context.executeTool(
                tool,
                JSON.stringify(args || {}),
                { signal: controller.signal }
            );
            return flatten(result);
        } finally {
            clearTimeout(timer);
        }
    }

    chrome.runtime.onMessage.addListener(function (message, sender, sendResponse) {
        if (message.type === 'REQUEST_TOOLS') {
            discover();
            return false;
        }

        if (message.type === 'EXECUTE_TOOL') {
            executeTool(message.data.toolName, message.data.args)
                .then(function (result) { sendResponse({ success: true, result: result }); })
                .catch(function (error) { sendResponse({ success: false, error: String((error && error.message) || error) }); });
            return true;
        }

        return false;
    });

    discover();
    document.addEventListener('DOMContentLoaded', function () { discover(); });
})();
