export type ConsentState = 'granted' | 'denied' | 'pending';

const CONSENT_COOKIE = 'hi_tracking_consent';
const CONSENT_MAX_AGE = 365 * 24 * 60 * 60; // 1 year

export function getConsentState(): ConsentState {
    if (typeof document === 'undefined') return 'pending';

    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${CONSENT_COOKIE}=([^;]*)`));
    if (!match) return 'pending';

    const value = match[1];
    if (value === 'granted' || value === 'denied') return value;
    return 'pending';
}

export function setConsentState(state: 'granted' | 'denied'): void {
    if (typeof document === 'undefined') return;
    const secure = window.location?.protocol === 'https:' ? ';Secure' : '';
    document.cookie = `${CONSENT_COOKIE}=${state};path=/;max-age=${CONSENT_MAX_AGE};SameSite=Lax${secure}`;
}

export function isConsentPending(): boolean {
    return getConsentState() === 'pending';
}

/**
 * Google Consent Mode v2 integration.
 * Must be called BEFORE any Google tags load.
 * Sets default denied state for EU compliance.
 */
export function initGoogleConsentMode(granted = false): void {
    if (typeof window === 'undefined') return;

    window.dataLayer = window.dataLayer || [];
    function gtag() {
        // eslint-disable-next-line prefer-rest-params
        window.dataLayer!.push(arguments);
    }

    const state = granted ? 'granted' : 'denied';
    // @ts-expect-error gtag uses arguments object
    gtag('consent', 'default', {
        ad_storage: state,
        analytics_storage: state,
        ad_user_data: state,
        ad_personalization: state,
        ...(!granted && {wait_for_update: 500}),
    });
}

export function updateGoogleConsentMode(granted: boolean): void {
    if (typeof window === 'undefined') return;

    window.dataLayer = window.dataLayer || [];
    function gtag() {
        // eslint-disable-next-line prefer-rest-params
        window.dataLayer!.push(arguments);
    }

    const state = granted ? 'granted' : 'denied';
    // @ts-expect-error gtag uses arguments object
    gtag('consent', 'update', {
        ad_storage: state,
        analytics_storage: state,
        ad_user_data: state,
        ad_personalization: state,
    });
}
