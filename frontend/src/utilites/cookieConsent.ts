import {getConfig} from './config.ts';

export type ConsentCategory = 'analytics' | 'advertising';
export type ConsentPreferences = Record<ConsentCategory, boolean>;

const CONSENT_COOKIE = 'hi_cookie_consent';
export const CONSENT_CHANGE_EVENT = 'hi_consent_change';
export const OPEN_SETTINGS_EVENT = 'hi_open_cookie_settings';

const CONSENT_MAX_AGE = 365 * 24 * 60 * 60;
const CATEGORIES: ConsentCategory[] = ['analytics', 'advertising'];

export const ALL_GRANTED: ConsentPreferences = {analytics: true, advertising: true};
export const ALL_DENIED: ConsentPreferences = {analytics: false, advertising: false};

export const isConsentBannerEnabled = (): boolean => getConfig('VITE_COOKIE_CONSENT_ENABLED') === 'true';

export const isEmbedded = (): boolean =>
    typeof window !== 'undefined' && (window.self !== window.top || window.location.pathname.startsWith('/widget'));

function parseConsent(value: string | null | undefined): ConsentPreferences | null {
    if (!value) return null;
    const params = new URLSearchParams(value);
    if (!CATEGORIES.some((category) => params.has(category))) return null;
    return {
        analytics: params.get('analytics') === '1',
        advertising: params.get('advertising') === '1',
    };
}

function serializeConsent(prefs: ConsentPreferences): string {
    return CATEGORIES.map((category) => `${category}=${prefs[category] ? '1' : '0'}`).join('&');
}

function cookieDomainFor(hostname: string, configuredDomain: string | undefined): string | null {
    const bare = (configuredDomain ?? '').trim().replace(/^\./, '');
    if (!bare) return null;
    return hostname === bare || hostname.endsWith(`.${bare}`) ? `.${bare}` : null;
}

export function readConsent(): ConsentPreferences | null {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${CONSENT_COOKIE}=([^;]*)`));
    return parseConsent(match?.[1]);
}

export function writeConsent(prefs: ConsentPreferences): void {
    if (typeof document === 'undefined') return;
    const domain = cookieDomainFor(window.location.hostname, getConfig('VITE_COOKIE_CONSENT_DOMAIN'));
    const domainAttribute = domain ? `;domain=${domain}` : '';
    const secure = window.location.protocol === 'https:' ? ';Secure' : '';
    document.cookie = `${CONSENT_COOKIE}=${serializeConsent(prefs)};path=/;max-age=${CONSENT_MAX_AGE};SameSite=Lax${domainAttribute}${secure}`;
    updateGoogleConsentMode(prefs);
    window.dispatchEvent(new CustomEvent<ConsentPreferences>(CONSENT_CHANGE_EVENT, {detail: prefs}));
}

function updateGoogleConsentMode(prefs: ConsentPreferences): void {
    const analytics = prefs.analytics ? 'granted' : 'denied';
    const advertising = prefs.advertising ? 'granted' : 'denied';
    window.gtag?.('consent', 'update', {
        ad_storage: advertising,
        ad_user_data: advertising,
        ad_personalization: advertising,
        analytics_storage: analytics,
    });
}

export function openCookieSettings(): void {
    window.dispatchEvent(new Event(OPEN_SETTINGS_EVENT));
}
