/**
 * Central registry of all AI-related API route URLs.
 * Change a path here and it propagates everywhere.
 */
const BASE = '/admin/ai/settings';

export const AI_ROUTES = {
    data:             `${BASE}/api/data`,
    saveProvider:     `${BASE}/api/provider`,
    deleteProvider:   (id) => `${BASE}/api/provider/${id}`,
    activateProvider: (id) => `${BASE}/api/provider/${id}/activate`,
    testProvider:     (id) => `${BASE}/api/provider/${id}/test`,
    testProviderStream: (id) => `${BASE}/api/provider/${id}/test?stream=1`,
    saveModel:        `${BASE}/api/model`,
    deleteModel:      (id) => `${BASE}/api/model/${id}`,
    activateModel:    (id) => `${BASE}/api/model/${id}/activate`,
    health:           `${BASE}/api/health`,
    usage:            '/admin/ai/usage/api/data',
    suggest:          '/admin/api/ai/suggest',
    suggestStream:    '/admin/api/ai/suggest/stream',
    fieldTypes:       '/admin/api/ai/field-types',
    languages:        '/admin/api/ai/languages',
};
