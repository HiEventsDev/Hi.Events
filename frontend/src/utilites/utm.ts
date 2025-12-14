export interface UtmData {
    utm_source: string | null;
    utm_medium: string | null;
    utm_campaign: string | null;
    utm_term: string | null;
    utm_content: string | null;
    referrer_url: string | null;
    landing_page: string | null;
    gclid: string | null;
    fbclid: string | null;
    utm_raw: Record<string, string> | null;
}

const UTM_STORAGE_KEY = 'hi_events_utm_first_touch';

export function captureUtmData(): void {
    if (typeof window === 'undefined') return;

    if (localStorage.getItem(UTM_STORAGE_KEY)) return;

    const params = new URLSearchParams(window.location.search);

    const rawParams: Record<string, string> = {};
    params.forEach((value, key) => {
        if (key.startsWith('utm_') || ['gclid', 'fbclid', 'ref'].includes(key)) {
            rawParams[key] = value;
        }
    });

    const utmData: UtmData = {
        utm_source: params.get('utm_source'),
        utm_medium: params.get('utm_medium'),
        utm_campaign: params.get('utm_campaign'),
        utm_term: params.get('utm_term'),
        utm_content: params.get('utm_content'),
        referrer_url: document.referrer || null,
        landing_page: window.location.href,
        gclid: params.get('gclid'),
        fbclid: params.get('fbclid'),
        utm_raw: Object.keys(rawParams).length > 0 ? rawParams : null,
    };

    if (hasUtmData(utmData)) {
        localStorage.setItem(UTM_STORAGE_KEY, JSON.stringify(utmData));
    }
}

export function getStoredUtmData(): UtmData | null {
    if (typeof window === 'undefined') return null;
    const stored = localStorage.getItem(UTM_STORAGE_KEY);
    return stored ? JSON.parse(stored) : null;
}

export function clearStoredUtmData(): void {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(UTM_STORAGE_KEY);
}

function hasUtmData(data: UtmData): boolean {
    return !!(
        data.utm_source ||
        data.utm_medium ||
        data.utm_campaign ||
        data.gclid ||
        data.fbclid
    );
}
