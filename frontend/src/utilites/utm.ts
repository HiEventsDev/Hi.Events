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
const PAID_CLICK_ID_PARAMS = ['gclid', 'gbraid', 'wbraid', 'fbclid'];
const RAW_PARAMS = [...PAID_CLICK_ID_PARAMS, 'gad_source', 'gad_campaignid', 'ref'];
const MAX_VALUE_LENGTH = 255;
const MAX_URL_LENGTH = 2048;

const clip = (value: string | null, max: number) => (value ? value.slice(0, max) : null);

export function captureUtmData(): void {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);

    const rawParams: Record<string, string> = {};
    params.forEach((value, key) => {
        if (key.startsWith('utm_') || RAW_PARAMS.includes(key)) {
            rawParams[key] = value;
        }
    });

    const utmData: UtmData = {
        utm_source: clip(params.get('utm_source'), MAX_VALUE_LENGTH),
        utm_medium: clip(params.get('utm_medium'), MAX_VALUE_LENGTH),
        utm_campaign: clip(params.get('utm_campaign'), MAX_VALUE_LENGTH),
        utm_term: clip(params.get('utm_term'), MAX_VALUE_LENGTH),
        utm_content: clip(params.get('utm_content'), MAX_VALUE_LENGTH),
        referrer_url: clip(params.get('referrer_url') || document.referrer, MAX_URL_LENGTH),
        landing_page: clip(params.get('landing_page') || window.location.href, MAX_URL_LENGTH),
        gclid: clip(params.get('gclid'), MAX_VALUE_LENGTH),
        fbclid: clip(params.get('fbclid'), MAX_VALUE_LENGTH),
        utm_raw: Object.keys(rawParams).length > 0 ? rawParams : null,
    };

    if (!hasUtmData(utmData)) return;

    const stored = getStoredUtmData();
    const upgradesToPaidClick = hasPaidClickId(utmData) && !hasPaidClickId(stored);

    if (!stored || upgradesToPaidClick) {
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
        hasPaidClickId(data)
    );
}

function hasPaidClickId(data: UtmData | null): boolean {
    return PAID_CLICK_ID_PARAMS.some((param) => !!data?.utm_raw?.[param]);
}
