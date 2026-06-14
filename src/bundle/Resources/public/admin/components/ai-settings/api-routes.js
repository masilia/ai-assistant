/**
 * Central registry of all AI-related API route URLs.
 * Change a path here and it propagates everywhere.
 */
const BASE = '/admin/ai/settings';

export const AI_ROUTES = {
    data:              `${BASE}/api/data`,
    saveProvider:      `${BASE}/api/provider`,
    deleteProvider:    (id) => `${BASE}/api/provider/${id}`,
    setSiteaccesses:   (id) => `${BASE}/api/provider/${id}/siteaccesses`,
    setChatModel:      (id) => `${BASE}/api/provider/${id}/chat-model`,
    setImageModel:     (id) => `${BASE}/api/provider/${id}/image-model`,
    testProvider:      (id) => `${BASE}/api/provider/${id}/test`,
    testProviderStream:(id) => `${BASE}/api/provider/${id}/test?stream=1`,
    saveModel:         `${BASE}/api/model`,
    deleteModel:       (id) => `${BASE}/api/model/${id}`,
    health:            `${BASE}/api/health`,
    usage:             '/admin/ai/usage/api/data',
    suggest:           '/admin/api/ai/suggest',
    suggestStream:     '/admin/api/ai/suggest/stream',
    generateImage:     '/admin/api/ai/generate-image',
    fieldTypes:        '/admin/api/ai/field-types',
    languages:         (contentId) => `/admin/api/ai/languages/${contentId}`,
    agentChat:         '/admin/api/ai/agent/chat',
    agentExecute:      '/admin/api/ai/agent/execute',
};
