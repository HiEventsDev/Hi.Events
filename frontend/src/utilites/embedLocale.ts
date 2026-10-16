import {availableLocales} from "../locales.ts";
import {getEmbedMode} from "./iframeResize.ts";
import {WIDGET_PARAM} from "./widgetConfig.ts";

const EMBED_LOCALE_KEY = 'hievents.embed.locale';

const isEmbedPath = (pathname: string) => pathname.startsWith('/widget/') || pathname.startsWith('/checkout/');

const normalise = (locale: string | null): string | null => {
    if (!locale) {
        return null;
    }
    const lower = locale.toLowerCase();
    return availableLocales.includes(lower) ? lower : null;
};

export const embedLocaleFromUrl = (pathname: string, search: string): string | null => {
    if (!isEmbedPath(pathname)) {
        return null;
    }
    const params = new URLSearchParams(search);
    const requested = params.get(WIDGET_PARAM.locale);
    if (requested === 'en' && params.get(WIDGET_PARAM.widgetVersion) === '1.0') {
        return null;
    }
    return normalise(requested);
};

export const embedLocaleForPath = (pathname: string, search: string, savedWidgetLocale?: string | null): string | null =>
    embedLocaleFromUrl(pathname, search) ?? (pathname.startsWith('/widget/') ? normalise(savedWidgetLocale ?? null) : null);

const hydratedSavedWidgetLocale = (): string | null => {
    const loaderData = (window as unknown as { __staticRouterHydrationData?: { loaderData?: Record<string, unknown> } })
        .__staticRouterHydrationData?.loaderData ?? {};
    const withPreset = Object.values(loaderData).find((data): data is { preset: { locale?: string } } =>
        !!data && typeof data === 'object' && !!(data as { preset?: { locale?: string } }).preset?.locale);
    return withPreset?.preset.locale ?? null;
};

export const resolveClientEmbedLocale = (): string | null => {
    if (typeof window === 'undefined') {
        return null;
    }
    const {pathname, search} = window.location;
    const fromUrl = embedLocaleForPath(pathname, search, hydratedSavedWidgetLocale());
    try {
        if (fromUrl) {
            window.sessionStorage.setItem(EMBED_LOCALE_KEY, fromUrl);
            return fromUrl;
        }
        if (pathname.startsWith('/checkout/') && getEmbedMode() === 'modal') {
            return normalise(window.sessionStorage.getItem(EMBED_LOCALE_KEY));
        }
    } catch {
        return fromUrl;
    }
    return null;
};

export const embedLocaleParam = (locale: string | null): Record<string, string> => locale ? {[WIDGET_PARAM.locale]: locale} : {};
