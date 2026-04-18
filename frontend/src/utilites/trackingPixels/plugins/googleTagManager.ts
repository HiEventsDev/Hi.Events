import {TrackingPixelPlugin, PageViewData, TrackingEventData} from '../types';

export const googleTagManagerPlugin: TrackingPixelPlugin = {
    name: 'google_tag_manager',

    initialize(containerId: string) {
        if (typeof window === 'undefined') return;
        if (document.querySelector('script[data-tracking-pixel="gtm"]')) return;

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'gtm.start': new Date().getTime(),
            event: 'gtm.js',
        });

        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(containerId)}`;
        script.dataset.trackingPixel = 'gtm';
        document.head.appendChild(script);
    },

    pageView(data: PageViewData) {
        window.dataLayer?.push({
            event: 'page_view',
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
        window.dataLayer?.push({
            event: eventMap[data.eventName] || data.eventName,
            value: data.value,
            currency: data.currency,
            transaction_id: data.transactionId ? String(data.transactionId) : undefined,
            item_name: data.contentName,
            item_id: data.contentId ? String(data.contentId) : undefined,
        });
    },

    cleanup() {
        document.querySelectorAll('script[data-tracking-pixel="gtm"]').forEach(el => el.remove());
    },
};
