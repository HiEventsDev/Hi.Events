import {facebookPixelPlugin} from './plugins/facebookPixel';
import {googleAnalytics4Plugin} from './plugins/googleAnalytics4';
import {googleTagManagerPlugin} from './plugins/googleTagManager';
import {tiktokPixelPlugin} from './plugins/tiktokPixel';
import {TrackingPixelPlugin, TrackingPixelConfig, PageViewData, TrackingEventData} from './types';

const pluginRegistry: Record<string, TrackingPixelPlugin> = {
    facebook_pixel: facebookPixelPlugin,
    google_analytics_4: googleAnalytics4Plugin,
    google_tag_manager: googleTagManagerPlugin,
    tiktok_pixel: tiktokPixelPlugin,
};

let activePlugins: TrackingPixelPlugin[] = [];

export function initializeTrackingPixels(pixels: TrackingPixelConfig[]): void {
    if (typeof window === 'undefined') return;
    cleanup();

    for (const pixel of pixels) {
        if (!pixel.enabled) continue;
        const plugin = pluginRegistry[pixel.provider];
        if (!plugin) {
            console.warn(`[hi.events] Unknown tracking pixel provider: ${pixel.provider}`);
            continue;
        }
        try {
            plugin.initialize(pixel.pixel_id);
            activePlugins.push(plugin);
        } catch (error) {
            console.error(`[hi.events] Failed to initialize ${pixel.provider}:`, error);
        }
    }
}

export function trackPageView(data?: Partial<PageViewData>): void {
    if (typeof window === 'undefined') return;
    const fullData: PageViewData = {
        url: window.location.href,
        title: document.title,
        referrer: document.referrer,
        ...data,
    };
    for (const plugin of activePlugins) {
        plugin.pageView(fullData);
    }
}

export function trackPixelEvent(data: TrackingEventData): void {
    if (typeof window === 'undefined') return;
    for (const plugin of activePlugins) {
        plugin.trackEvent(data);
    }
}

export function cleanup(): void {
    if (typeof window === 'undefined') return;
    for (const plugin of activePlugins) {
        plugin.cleanup?.();
    }
    activePlugins = [];
}

export function hasActivePixels(): boolean {
    return activePlugins.length > 0;
}
