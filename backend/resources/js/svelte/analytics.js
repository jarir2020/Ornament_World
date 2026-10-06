/**
 * Provider-neutral browser event layer.
 *
 * Events are queued and dispatched locally. An analytics provider can attach
 * an adapter later without changing product, cart, checkout, or order UI.
 * Payloads deliberately contain catalog/order totals only, never customer PII.
 */
export function trackEvent(name, params = {}) {
    if (typeof window === 'undefined' || !name) return;

    const event = {
        event: name,
        params,
        timestamp: new Date().toISOString(),
    };
    window.__ornamentsAnalyticsQueue = window.__ornamentsAnalyticsQueue || [];
    window.__ornamentsAnalyticsQueue.push(event);
    window.dispatchEvent(new CustomEvent('ornaments:analytics', { detail: event }));

    const adapters = window.__ornamentsAnalyticsAdapters || [];
    adapters.forEach((adapter) => {
        try {
            if (typeof adapter === 'function') adapter(event);
            else if (typeof adapter.track === 'function') adapter.track(event);
        } catch (error) {
            console.warn('Analytics adapter failed.', error);
        }
    });
}

export function trackOnce(key, name, params = {}) {
    if (typeof window === 'undefined' || !key) return;

    const storageKey = `ornaments_analytics_${key}`;
    try {
        if (sessionStorage.getItem(storageKey)) return;
        sessionStorage.setItem(storageKey, '1');
    } catch (error) {
        // A blocked session store should not stop a customer journey.
    }
    trackEvent(name, params);
}

export function registerAnalyticsAdapter(adapter) {
    if (typeof window === 'undefined' || (!adapter && typeof adapter !== 'function')) return () => {};
    window.__ornamentsAnalyticsAdapters = window.__ornamentsAnalyticsAdapters || [];
    window.__ornamentsAnalyticsAdapters.push(adapter);
    return () => {
        window.__ornamentsAnalyticsAdapters = window.__ornamentsAnalyticsAdapters.filter((item) => item !== adapter);
    };
}
