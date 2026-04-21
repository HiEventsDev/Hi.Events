import {TrackingPixelPlugin, PageViewData, TrackingEventData} from '../types';

declare global {
    interface Window {
        gtag?: (...args: unknown[]) => void;
        dataLayer?: unknown[];
    }
}

export const googleAnalytics4Plugin: TrackingPixelPlugin = {
    name: 'google_analytics_4',

    initialize(measurementId: string) {
        if (typeof window === 'undefined') return;
        if (document.querySelector('script[data-tracking-pixel="ga4"]')) return;

        window.dataLayer = window.dataLayer || [];
        if (!window.gtag) {
            window.gtag = function () {
                // Must use arguments object, not rest params — gtag.js expects this format
                // eslint-disable-next-line prefer-rest-params
                window.dataLayer!.push(arguments);
            };
        }
        window.gtag('js', new Date());
        window.gtag('config', measurementId, {send_page_view: false});

        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
        script.dataset.trackingPixel = 'ga4';
        document.head.appendChild(script);
    },

    pageView(data: PageViewData) {
        window.gtag?.('event', 'page_view', {
            page_location: data.url,
            page_title: data.title,
            page_referrer: data.referrer,
        });
    },

    trackEvent(data: TrackingEventData) {
        const eventMap: Record<string, string> = {
            'ViewContent': 'view_item',
            'InitiateCheckout': 'begin_checkout',
            'Purchase': 'purchase',
        };
        const gaEvent = eventMap[data.eventName] || data.eventName;
        window.gtag?.('event', gaEvent, {
            value: data.value,
            currency: data.currency,
            transaction_id: data.transactionId ? String(data.transactionId) : undefined,
            items: data.contentId ? [{item_id: String(data.contentId), item_name: data.contentName}] : undefined,
        });
    },

    cleanup() {
        document.querySelectorAll('script[data-tracking-pixel="ga4"]').forEach(el => el.remove());
        delete (window as any).gtag;
    },
};
