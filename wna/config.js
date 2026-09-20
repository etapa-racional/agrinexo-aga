// A key must never collide with a model name's first ':' segment.

const DEFAULT_INSTANCE = 'local';

const INSTANCES = {
    local: {
        base_url: 'http://localhost:11434/v1',
        needs_key: false,           // Ollama ignores Authorization entirely
        temperature: 0.2
    },

    remote: {
        base_url: 'https://api.featherless.ai/v1',
        needs_key: true,
        temperature: 0.2
    }
};
