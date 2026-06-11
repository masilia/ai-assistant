import { useState, useEffect, useCallback } from 'react';
import { AI_ROUTES } from './api-routes.js';
import { notify, cleanErrorMessage } from './constants.js';

/**
 * @typedef {import('./types.js').Provider} Provider
 * @typedef {import('./types.js').Model} Model
 * @typedef {import('./types.js').TestResult} TestResult
 * @typedef {import('./types.js').DashboardData} DashboardData
 */

/**
 * Custom hook — owns all AI settings state, data fetching, and CRUD logic.
 * AiSettingsDashboard becomes a pure presentation component.
 *
 * @returns {{
 *   data: DashboardData,
 *   loading: boolean,
 *   submitting: boolean,
 *   testingId: number|null,
 *   testResults: Object<number, TestResult>,
 *   fetchData: () => Promise<DashboardData|undefined>,
 *   saveProvider: (e: Event, editing: Provider|'new'|null) => Promise<boolean>,
 *   deleteProvider: (id: number) => Promise<void>,
 *   testProvider: (id: number) => Promise<void>,
 *   saveModel: (e: Event, editing: Model|'new'|null) => Promise<boolean>,
 *   deleteModel: (id: number) => Promise<void>,
 *   setSiteaccesses: (providerId: number, siteaccesses: string[]) => Promise<void>,
 *   setChatModel: (providerId: number, modelId: number|null) => Promise<void>,
 *   setImageModel: (providerId: number, modelId: number|null) => Promise<void>,
 * }}
 */
export function useAiSettings() {
    const [data, setData]           = useState(/** @type {DashboardData} */ ({
        providers: [], models: [], siteaccesses: [], currentSiteaccess: '',
    }));
    const [loading, setLoading]     = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [testingId, setTestingId] = useState(/** @type {number|null} */ (null));
    const [testResults, setTestResults] = useState(/** @type {Object<number, TestResult>} */ ({}));

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

    const testProvider = useCallback(async (id) => {
        setTestingId(id);
        setTestResults(prev => ({ ...prev, [id]: null }));
        try {
            const res = await fetch(AI_ROUTES.testProviderStream(id), { method: 'POST' });
            const result = await res.json();
            const success = res.ok && result.success;
            const baseMessage = cleanErrorMessage(result.message || result.error || 'Connection failed.');
            const streamNote = result.streamTested && result.streamOk === false
                ? ' (streaming path also failed)'
                : (result.streamTested && result.streamOk ? ' (streaming path OK)' : '');
            const message = baseMessage + streamNote;
            setTestResults(prev => ({ ...prev, [id]: { success, message, streamOk: result.streamOk } }));
            if (success) {
                notify('success', result.streamOk
                    ? 'Connection test succeeded: sync + streaming both OK.'
                    : 'Connection test succeeded: endpoint reachable.');
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

    // ── Siteaccess / model assignment ──────────────────────────────────────
    const setSiteaccesses = useCallback(async (providerId, siteaccesses) => {
        const previous = data;
        // Optimistic: update local state immediately
        setData((prev) => ({
            ...prev,
            providers: prev.providers.map((p) =>
                p.id === providerId ? { ...p, siteaccesses } : p
            ),
        }));
        try {
            const res = await fetch(AI_ROUTES.setSiteaccesses(providerId), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ siteaccesses }),
            });
            if (!res.ok) throw new Error('Failed to update siteaccess assignments.');
            await fetchData();
        } catch (err) {
            setData(previous);
            await fetchData();
            notify('error', cleanErrorMessage(err.message));
        }
    }, [data, fetchData]);

    const setChatModel = useCallback(async (providerId, modelId) => {
        const previous = data;
        setData((prev) => ({
            ...prev,
            providers: prev.providers.map((p) =>
                p.id === providerId ? { ...p, activeChatModelId: modelId } : p
            ),
        }));
        try {
            const res = await fetch(AI_ROUTES.setChatModel(providerId), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modelId }),
            });
            if (!res.ok) throw new Error('Failed to update chat model.');
            await fetchData();
        } catch (err) {
            setData(previous);
            await fetchData();
            notify('error', cleanErrorMessage(err.message));
        }
    }, [data, fetchData]);

    const setImageModel = useCallback(async (providerId, modelId) => {
        const previous = data;
        setData((prev) => ({
            ...prev,
            providers: prev.providers.map((p) =>
                p.id === providerId ? { ...p, activeImageModelId: modelId } : p
            ),
        }));
        try {
            const res = await fetch(AI_ROUTES.setImageModel(providerId), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modelId }),
            });
            if (!res.ok) throw new Error('Failed to update image model.');
            await fetchData();
        } catch (err) {
            setData(previous);
            await fetchData();
            notify('error', cleanErrorMessage(err.message));
        }
    }, [data, fetchData]);

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
            supportsImage: fd.get('supportsImage') === 'true',
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

    return {
        data,
        loading,
        submitting,
        testingId,
        testResults,
        fetchData,
        saveProvider,
        deleteProvider,
        testProvider,
        saveModel,
        deleteModel,
        setSiteaccesses,
        setChatModel,
        setImageModel,
    };
}
