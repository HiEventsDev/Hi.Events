import {useEffect, useState} from 'react';
import {
    ALL_GRANTED,
    CONSENT_CHANGE_EVENT,
    ConsentPreferences,
    isConsentBannerEnabled,
    readConsent,
} from '../utilites/cookieConsent';

export function useCookieConsent(): ConsentPreferences | null | undefined {
    const [prefs, setPrefs] = useState<ConsentPreferences | null | undefined>(undefined);

    useEffect(() => {
        if (!isConsentBannerEnabled()) {
            setPrefs(ALL_GRANTED);
            return;
        }

        setPrefs(readConsent());

        const handler = (event: Event) => setPrefs((event as CustomEvent<ConsentPreferences>).detail);
        window.addEventListener(CONSENT_CHANGE_EVENT, handler);
        return () => window.removeEventListener(CONSENT_CHANGE_EVENT, handler);
    }, []);

    return prefs;
}
