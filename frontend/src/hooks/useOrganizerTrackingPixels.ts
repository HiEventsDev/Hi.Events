import {useEffect, useMemo, useState, useCallback, useRef} from 'react';
import {TrackingPixelConfig} from '../types';
import {initializeTrackingPixels, trackPageView, cleanup} from '../utilites/trackingPixels';
import {getConsentState, setConsentState, isConsentPending, initGoogleConsentMode, updateGoogleConsentMode} from '../utilites/trackingPixels/consent';
import {getConfig} from '../utilites/config';

interface UseOrganizerTrackingPixelsReturn {
    consentPending: boolean;
    consentGranted: boolean;
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

    // Avoid SSR hydration mismatch by defaulting to false and reading cookie on mount
    const [consentGranted, setConsentGranted] = useState(false);
    const consentModeInitialized = useRef(false);

    // On mount: read consent state and initialize Google Consent Mode synchronously
    useEffect(() => {
        const currentConsent = getConsentState() === 'granted';
        setConsentGranted(currentConsent);

        if (hasPixels && !consentModeInitialized.current) {
            initGoogleConsentMode(currentConsent);
            consentModeInitialized.current = true;
        }
    }, [hasPixels]);

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

    // Initialize pixels when consent is granted
    useEffect(() => {
        if (!hasPixels || !consentGranted) return;

        initializeTrackingPixels(trackingPixels!);

        // Delay pageview to allow vendor scripts to load asynchronously
        const timer = setTimeout(() => {
            trackPageView();
        }, 100);

        return () => {
            clearTimeout(timer);
            cleanup();
        };
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
        consentGranted,
        onConsent,
    };
}
