declare global {
    interface Window {
        fathom?: {
            trackEvent: (eventName: string, options?: { _value?: number }) => void;
            trackPageview: () => void;
        };
    }
}

interface TrackEventOptions {
    value?: number;
}

export const AnalyticsEvents = {
    SIGNUP_COMPLETED: 'signup_completed',
    ORGANIZER_CREATED: 'organizer_created',
    EVENT_PUBLISHED: 'event_published',
    STRIPE_CONNECTED: 'stripe_connected',
    PURCHASE_COMPLETED_PAID: 'purchase_completed_paid',
    PURCHASE_COMPLETED_OFFLINE: 'purchase_completed_offline',
    PURCHASE_COMPLETED_FREE: 'purchase_completed_free',
} as const;

export type AnalyticsEventName = typeof AnalyticsEvents[keyof typeof AnalyticsEvents];

export function trackEvent(eventName: AnalyticsEventName | string, options?: TrackEventOptions): void {
    if (typeof window === 'undefined') {
        return;
    }
    // Fathom Analytics
    if (window.fathom?.trackEvent) {
        const fathomOptions = options?.value ? { _value: options.value } : undefined;
        window.fathom.trackEvent(eventName, fathomOptions);
    }

    // Future: Google Analytics 4
    // if (window.gtag) {
    //     window.gtag('event', eventName, {
    //         value: options?.value ? options.value / 100 : undefined,
    //     });
    // }
}
