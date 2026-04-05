declare global {
    interface Window {
        fathom?: {
            trackEvent: (eventName: string, options?: { _value?: number }) => void;
            trackPageview: () => void;
        };
        fbq?: (...args: any[]) => void;
    }
}

interface TrackEventOptions {
    value?: number;
    currency?: string;
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

let metaPixelInitialized = false;

export function initMetaPixel(pixelId: string): void {
    if (typeof window === 'undefined' || metaPixelInitialized) {
        return;
    }

    // Validate pixel ID is numeric only
    if (!/^\d+$/.test(pixelId)) {
        return;
    }

    metaPixelInitialized = true;

    // Initialize fbq
    const f = window as any;
    const n = f.fbq = function (...args: any[]) {
        n.callMethod ? n.callMethod(...args) : n.queue.push(args);
    };
    if (!f._fbq) f._fbq = n;
    n.push = n;
    n.loaded = true;
    n.version = '2.0';
    n.queue = [];

    // Load the pixel script
    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://connect.facebook.net/en_US/fbevents.js';
    const firstScript = document.getElementsByTagName('script')[0];
    firstScript?.parentNode?.insertBefore(script, firstScript);

    // Initialize with pixel ID
    window.fbq?.('init', pixelId);
    window.fbq?.('track', 'PageView');
}

export function trackEvent(eventName: AnalyticsEventName | string, options?: TrackEventOptions): void {
    if (typeof window === 'undefined') {
        return;
    }
    // Fathom Analytics
    if (window.fathom?.trackEvent) {
        const fathomOptions = options?.value ? { _value: options.value } : undefined;
        window.fathom.trackEvent(eventName, fathomOptions);
    }

    // Meta Pixel
    if (window.fbq) {
        const metaEventMap: Record<string, string> = {
            [AnalyticsEvents.PURCHASE_COMPLETED_PAID]: 'Purchase',
            [AnalyticsEvents.PURCHASE_COMPLETED_OFFLINE]: 'Purchase',
            [AnalyticsEvents.PURCHASE_COMPLETED_FREE]: 'Purchase',
        };

        const metaEvent = metaEventMap[eventName];
        if (metaEvent) {
            const metaParams: Record<string, any> = {};
            if (options?.value) {
                metaParams.value = options.value / 100; // Convert cents to dollars
                metaParams.currency = options.currency || 'USD';
            }
            window.fbq('track', metaEvent, metaParams);
        }
    }

    // Future: Google Analytics 4
    // if (window.gtag) {
    //     window.gtag('event', eventName, {
    //         value: options?.value ? options.value / 100 : undefined,
    //     });
    // }
}
