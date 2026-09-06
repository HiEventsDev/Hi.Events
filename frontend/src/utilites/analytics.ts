import {getConfig} from './config.ts';

declare global {
    interface Window {
        fathom?: {
            trackEvent: (eventName: string, options?: { _value?: number }) => void;
            trackPageview: () => void;
        };
        gtag?: (...args: unknown[]) => void;
    }
}

interface TrackEventOptions {
    value?: number;
}

export const AnalyticsEvents = {
    SIGNUP_COMPLETED: 'signup_completed',
    ORGANIZER_CREATED: 'organizer_created',
    FIRST_EVENT_CREATED: 'first_event_created',
    EVENT_PUBLISHED: 'event_published',
    STRIPE_CONNECTED: 'stripe_connected',
    PURCHASE_COMPLETED_PAID: 'purchase_completed_paid',
    PURCHASE_COMPLETED_OFFLINE: 'purchase_completed_offline',
    PURCHASE_COMPLETED_FREE: 'purchase_completed_free',
} as const;

export type AnalyticsEventName = typeof AnalyticsEvents[keyof typeof AnalyticsEvents];

function getGoogleAdsConversionLabels(): Record<string, string> {
    const raw = getConfig('VITE_GOOGLE_ADS_CONVERSION_LABELS');
    if (!raw) {
        return {};
    }

    return raw.split(',').reduce<Record<string, string>>((labels, pair) => {
        const [eventName, label] = pair.split(':').map((part) => part.trim());
        if (eventName && label) {
            labels[eventName] = label;
        }
        return labels;
    }, {});
}

function trackGoogleAdsConversion(eventName: string): void {
    const conversionId = getConfig('VITE_GOOGLE_ADS_CONVERSION_ID');
    const label = getGoogleAdsConversionLabels()[eventName];

    if (!conversionId || !label || !window.gtag) {
        return;
    }

    window.gtag('event', 'conversion', {send_to: `${conversionId}/${label}`});
}

export function trackEvent(eventName: AnalyticsEventName | string, options?: TrackEventOptions): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.fathom?.trackEvent) {
        const fathomOptions = options?.value ? { _value: options.value } : undefined;
        window.fathom.trackEvent(eventName, fathomOptions);
    }

    trackGoogleAdsConversion(eventName);
}
