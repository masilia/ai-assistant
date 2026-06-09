import { AI_ROUTES } from '../../components/ai-settings/api-routes.js';

/**
 * Immediate fallback used before (or if) the authoritative list is fetched
 * from the backend. The backend FieldFormatResolver is the single source of
 * truth; this only mirrors it so buttons can render without waiting on a
 * network round-trip.
 */
export const DEFAULT_SUPPORTED_FIELDS = {
    'ibexa-field-edit--ezstring':      'ezstring',
    'ibexa-field-edit--eztext':        'eztext',
    'ibexa-field-edit--ezrichtext':    'ezrichtext',
    'ibexa-field-edit--novaseometas':  'novaseometas',
    'ibexa-field-edit--ezmatrix':      'ezmatrix',
};

/**
 * Read lazily so a list fetched/assigned after initial load is picked up by
 * subsequent scans.
 */
export const getSupportedFields = () => window.AI_SUPPORTED_FIELDS || DEFAULT_SUPPORTED_FIELDS;

/**
 * Fetch the authoritative supported-field map from the backend
 * (FieldFormatResolver, the single source of truth) and re-scan so any
 * field types not covered by DEFAULT_SUPPORTED_FIELDS get buttons too.
 * Silently keeps the local fallback on any error.
 *
 * @param {() => void} onUpdate  Called when the map is updated.
 */
export function fetchSupportedFields(onUpdate) {
    fetch(AI_ROUTES.fieldTypes, { headers: { Accept: 'application/json' } })
        .then((res) => (res.ok ? res.json() : null))
        .then((data) => {
            const map = data && data.fieldTypes;
            if (map && typeof map === 'object' && Object.keys(map).length > 0) {
                window.AI_SUPPORTED_FIELDS = map;
                if (typeof onUpdate === 'function') onUpdate();
            }
        })
        .catch(() => { /* keep DEFAULT_SUPPORTED_FIELDS */ });
}
