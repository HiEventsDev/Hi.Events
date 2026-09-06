import {useEffect, useMemo} from 'react';
import {TrackingPixelConfig} from '../types';
import {cleanup, initializeTrackingPixels, pixelConsentCategory, trackPageView} from '../utilites/trackingPixels';
import {useCookieConsent} from './useCookieConsent';

interface UseOrganizerTrackingPixelsReturn {
    pixelsReady: boolean;
}

export function useOrganizerTrackingPixels(
    trackingPixels: TrackingPixelConfig[] | undefined
): UseOrganizerTrackingPixelsReturn {
    const prefs = useCookieConsent();

    const allowedKey = JSON.stringify((trackingPixels ?? []).filter((pixel) => {
        const category = pixelConsentCategory[pixel.provider];
        return pixel.enabled && category !== undefined && !!prefs?.[category];
    }));
    const allowed = useMemo<TrackingPixelConfig[]>(() => JSON.parse(allowedKey), [allowedKey]);

    useEffect(() => {
        if (allowed.length === 0) return;

        initializeTrackingPixels(allowed);
        const timer = setTimeout(() => trackPageView(), 100);

        return () => {
            clearTimeout(timer);
            cleanup();
        };
    }, [allowed]);

    return {pixelsReady: allowed.length > 0};
}
