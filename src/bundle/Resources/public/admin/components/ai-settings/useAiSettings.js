import { useState, useEffect, useCallback } from 'react';
import { AI_ROUTES } from './api-routes.js';
import { notify, cleanErrorMessage } from './constants.js';

/**
 * Custom hook — owns all AI settings state, data fetching, and CRUD logic.
 * AiSettingsDashboard becomes a pure presentation component.
 */
export function useAiSettings() {
    const [data, setData]           = useState({ providers: [], models: [], activeProviderId: null, activeModelId: null });
    const [loading, setLoading]     = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [testingId, setTestingId] = useState(null);
    const [testResults, setTestResults] = useState({});

    // ── Data fetching ──────────────────────────────────────────────────────
    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(AI_ROUTES.data);
            if (!res.ok) throw new Error('Failed to load AI settings data');
            const json = await res.json();
            setData(json);
            return json;
        } catch (err) {
            notify('error', cleanErrorMessage(err.message));
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { fetchData(); }, [fetchData]);

    // ── Provider CRUD ──────────────────────────────────────────────────────
    const saveProvider = useCallback(async (formEvent, editingProvider) => {
        formEvent.preventDefault();
        setSubmitting(true);
        const fd = new FormData(formEvent.target);
        const payload = {
            id:         editingProvider && editingProvider !== 'new' ? editingProvider.id : null,
            name:       fd.get('name'),
            identifier: fd.get('identifier'),
            apiKey:     fd.get('apiKey'),
            apiUrl:     fd.get('apiUrl'),
            isActive:   fd.get('isActive') === 'true',
        };
        try {
            const res = await fetch(AI_ROUTES.saveProvider, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const result = await res.json();
            if (!res.ok) throw new Error(result.error || 'Failed to save provider.');
            notify('success', 'Provider saved successfully.');
            await fetchData();
            return true;
        } catch (err) {
            notify('error', cleanErrorMessage(err.message));
            return false;
        } finally {
            setSubmitting(false);
        }
    }, [fetchData]);

    const deleteProvider = useCallback(async (id) => {
        try {
            const res = await fetch(AI_ROUTES.deleteProvider(id), { method: 'DELETE' });
            if (!res.ok) throw new Error('Failed to delete provider.');
            notify('success', 'Provider deleted successfully.');
            await fetchData();
        } catch (err) {
            notify('error', cleanErrorMessage(err.message));
        }
    }, [fetchData]);

    const activateProvider = useCallback(async (id) => {
        try {
            const res = await fetch(AI_ROUTES.activateProvider(id), { method: 'POST' });
            if (!res.ok) throw new Error('Failed to activate provider.');
            notify('success', 'Active provider routing updated.');
            await fetchData();
        } catch (err) {
            notify('error', cleanErrorMessage(err.message));
        }
    }, [fetchData]);

    const testProvider = useCallback(async (id) => {
        setTestingId(id);
        setTestResults(prev => ({ ...prev, [id]: null }));
        try {
            const res = await fetch(AI_ROUTES.testProvider(id), { method: 'POST' });
            const result = await res.json();
            const success = res.ok && result.success;
            const message = cleanErrorMessage(result.message || result.error || 'Connection failed.');
            setTestResults(prev => ({ ...prev, [id]: { success, message } }));
            if (success) {
                notify('success', 'Connection test succeeded: Endpoint is reachable.');
            } else {
                notify('error', `Connection test failed: ${message}`);
            }
        } catch (err) {
            setTestResults(prev => ({ ...prev, [id]: { success: false, message: 'Network error.' } }));
            notify('error', `Connection test failed: ${cleanErrorMessage(err.message)}`);
        } finally {
            setTestingId(null);
        }
    }, []);

    // ── Model CRUD ─────────────────────────────────────────────────────────
    const saveModel = useCallback(async (formEvent, editingModel) => {
        formEvent.preventDefault();
        setSubmitting(true);
        const fd = new FormData(formEvent.target);
        const payload = {
            id:          editingModel && editingModel !== 'new' ? editingModel.id : null,
            providerId:  parseInt(fd.get('providerId'), 10),
            name:        fd.get('name'),
            identifier:  fd.get('identifier'),
            temperature: parseFloat(fd.get('temperature')),
            maxTokens:   parseInt(fd.get('maxTokens'), 10),
            isActive:    fd.get('isActive') === 'true',
        };
        try {
            const res = await fetch(AI_ROUTES.saveModel, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const result = await res.json();
            if (!res.ok) throw new Error(result.error || 'Failed to save model.');
            notify('success', 'Model configuration saved.');
            await fetchData();
            return true;
        } catch (err) {
            notify('error', cleanErrorMessage(err.message));
            return false;
        } finally {
            setSubmitting(false);
        }
    }, [fetchData]);

    const deleteModel = useCallback(async (id) => {
        try {
            const res = await fetch(AI_ROUTES.deleteModel(id), { method: 'DELETE' });
            if (!res.ok) throw new Error('Failed to delete model.');
            notify('success', 'Model configuration deleted.');
            await fetchData();
        } catch (err) {
            notify('error', cleanErrorMessage(err.message));
        }
    }, [fetchData]);

    const activateModel = useCallback(async (id) => {
        try {
            const res = await fetch(AI_ROUTES.activateModel(id), { method: 'POST' });
            if (!res.ok) throw new Error('Failed to activate model.');
            notify('success', 'Active model routing updated.');
            await fetchData();
        } catch (err) {
            notify('error', cleanErrorMessage(err.message));
        }
    }, [fetchData]);

    return {
        data,
        loading,
        submitting,
        testingId,
        testResults,
        fetchData,
        saveProvider,
        deleteProvider,
        activateProvider,
        testProvider,
        saveModel,
        deleteModel,
        activateModel,
    };
}
