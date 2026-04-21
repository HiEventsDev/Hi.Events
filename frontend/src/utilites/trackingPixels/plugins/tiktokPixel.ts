import {TrackingPixelPlugin, PageViewData, TrackingEventData} from '../types';

declare global {
    interface Window {
        ttq?: any;
        TiktokAnalyticsObject?: string;
    }
}

export const tiktokPixelPlugin: TrackingPixelPlugin = {
    name: 'tiktok_pixel',

    initialize(pixelId: string) {
        if (typeof window === 'undefined' || window.ttq) return;

        const ttq = (window.ttq = window.ttq || []) as any;
        ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'];
        ttq.setAndDefer = function (t: any, e: string) {
            t[e] = function (...args: unknown[]) {
                t.push([e, ...args]);
            };
        };
        for (const method of ttq.methods) {
            ttq.setAndDefer(ttq, method);
        }
        ttq.instance = function (id: string) {
            const instance = ttq._i[id] || [];
            for (const method of ttq.methods) {
                ttq.setAndDefer(instance, method);
            }
            return instance;
        };
        ttq.load = function (id: string) {
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.async = true;
            script.src = 'https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=' + encodeURIComponent(id) + '&lib=ttq';
            script.dataset.trackingPixel = 'tiktok';
            document.head.appendChild(script);
        };
        ttq._i = ttq._i || {};
        ttq._t = ttq._t || {};

        ttq.load(pixelId);
        ttq.page();
    },

    pageView(_data: PageViewData) {
        window.ttq?.page();
    },

    trackEvent(data: TrackingEventData) {
        const eventMap: Record<string, string> = {
            'ViewContent': 'ViewContent',
            'InitiateCheckout': 'InitiateCheckout',
            'Purchase': 'CompletePayment',
        };
        const ttEvent = eventMap[data.eventName] || data.eventName;
        window.ttq?.track(ttEvent, {
            value: data.value,
            currency: data.currency,
            content_name: data.contentName,
            content_id: data.contentId ? String(data.contentId) : undefined,
        });
    },

    cleanup() {
        document.querySelectorAll('script[data-tracking-pixel="tiktok"]').forEach(el => el.remove());
        delete (window as any).ttq;
        delete (window as any).TiktokAnalyticsObject;
    },
};
