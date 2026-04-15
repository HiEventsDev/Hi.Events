import {useEffect, useMemo, useState, useCallback} from 'react';
import {TrackingPixelConfig} from '../types';
import {initializeTrackingPixels, trackPageView, cleanup} from '../utilites/trackingPixels';
import {getConsentState, setConsentState, isConsentPending, initGoogleConsentMode, updateGoogleConsentMode} from '../utilites/trackingPixels/consent';
import {getConfig} from '../utilites/config';

interface UseOrganizerTrackingPixelsReturn {
    consentPending: boolean;
    onConsent: (granted: boolean) => void;
}

export function useOrganizerTrackingPixels(
    trackingPixels: TrackingPixelConfig[] | undefined
): UseOrganizerTrackingPixelsReturn {
    const hasPixels = !!trackingPixels?.length;
    const pixelsKey = useMemo(
        () => JSON.stringify(trackingPixels ?? []),
        [trackingPixels]
    );
    const [consentGranted, setConsentGranted] = useState(
        () => getConsentState() === 'granted'
    );

    // Listen for consent changes from the global banner
    useEffect(() => {
        if (typeof window === 'undefined') return;

        const handler = (e: Event) => {
            const granted = (e as CustomEvent).detail?.granted;
            setConsentGranted(granted === true);
        };

        window.addEventListener('hi_consent_change', handler);
        return () => window.removeEventListener('hi_consent_change', handler);
    }, []);

    // Set Google Consent Mode defaults before any tags load
    // Only set denied defaults when consent hasn't been granted yet
    useEffect(() => {
        if (!hasPixels || consentGranted) return;
        initGoogleConsentMode();
    }, [hasPixels, consentGranted]);

    // Initialize pixels when consent is granted
    useEffect(() => {
        if (!hasPixels || !consentGranted) return;

        updateGoogleConsentMode(true);
        initializeTrackingPixels(trackingPixels!);
        trackPageView();

        return () => cleanup();
    }, [pixelsKey, consentGranted]);

    const onConsent = useCallback((granted: boolean) => {
        setConsentState(granted ? 'granted' : 'denied');
        updateGoogleConsentMode(granted);
        setConsentGranted(granted);
    }, []);

    // Don't show per-page banner if the global banner is already handling consent
    const globalBannerEnabled = getConfig('VITE_COOKIE_CONSENT_ENABLED') === 'true';
    const showBanner = hasPixels && !globalBannerEnabled && isConsentPending();

    return {
        consentPending: showBanner,
        onConsent,
    };
}
