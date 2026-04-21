import {TrackingPixelPlugin, PageViewData, TrackingEventData} from '../types';

declare global {
    interface Window {
        fbq?: (...args: any[]) => void;
        _fbq?: unknown;
    }
}

function fbq(...args: unknown[]) {
    if (typeof window !== 'undefined' && window.fbq) {
        window.fbq(...args);
    }
}

export const facebookPixelPlugin: TrackingPixelPlugin = {
    name: 'facebook_pixel',

    initialize(pixelId: string) {
        if (typeof window === 'undefined' || window.fbq) return;

        const f = window as any;
        const n = (f.fbq = function (...args: unknown[]) {
            if (n.callMethod) {
                n.callMethod(...args);
            } else {
                n.queue.push(args);
            }
        }) as any;
        if (!f._fbq) f._fbq = n;
        n.push = n;
        n.loaded = true;
        n.version = '2.0';
        n.queue = [] as unknown[];
        n.callMethod = undefined;

        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://connect.facebook.net/en_US/fbevents.js';
        script.dataset.trackingPixel = 'facebook';
        document.head.appendChild(script);

        fbq('init', pixelId);
    },

    pageView(_data: PageViewData) {
        fbq('track', 'PageView');
    },

    trackEvent(data: TrackingEventData) {
        const eventMap: Record<string, string> = {
            'ViewContent': 'ViewContent',
            'InitiateCheckout': 'InitiateCheckout',
            'Purchase': 'Purchase',
        };
        const fbEvent = eventMap[data.eventName] || data.eventName;
        fbq('track', fbEvent, {
            value: data.value || 0,
            currency: data.currency || 'USD',
            content_name: data.contentName,
            content_ids: data.contentId ? [String(data.contentId)] : undefined,
        });
    },

    cleanup() {
        document.querySelectorAll('script[data-tracking-pixel="facebook"]').forEach(el => el.remove());
        delete (window as any).fbq;
        delete (window as any)._fbq;
    },
};
