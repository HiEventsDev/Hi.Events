import {useEffect, useMemo, useState} from "react";
import {useLoaderData, useLocation} from "react-router";
import {HomepageThemeSettings} from "../../types.ts";
import {WidgetLoaderData} from "../../routeLoaders/widgetRouteLoader.ts";
import {forwardedTrackingParams, resolveWidgetConfig, WIDGET_PARAM, WidgetRuntimeConfig, WidgetSettings} from "../../utilites/widgetConfig.ts";
import {
    resolveWidgetColors,
    resolveWidgetFont,
    WidgetColors,
    widgetColorMode,
    widgetFrameStyle
} from "../../utilites/widgetTheme.ts";
import {ensureHomepageFontLoaded} from "../../utilites/fontLoader.ts";
import {embedLocaleForPath, embedLocaleParam} from "../../utilites/embedLocale.ts";
import {dynamicActivateLocale, getSupportedLocale} from "../../locales.ts";

export const PREVIEW_SETTINGS_MESSAGE = 'hievents:preview-settings';
export const PREVIEW_READY_MESSAGE = 'hievents:preview-ready';
export const PREVIEW_OPEN_EVENT_MESSAGE = 'hievents:preview-open-event';

const usePreviewSettings = (isPreview: boolean) => {
    const [settings, setSettings] = useState<WidgetSettings | null>(null);

    useEffect(() => {
        if (!isPreview || window.parent === window) {
            return;
        }
        const onMessage = (event: MessageEvent) => {
            if (event.origin === window.location.origin && event.source === window.parent && event.data?.type === PREVIEW_SETTINGS_MESSAGE) {
                setSettings(event.data.settings ?? {});
            }
        };
        window.addEventListener('message', onMessage);
        window.parent.postMessage({type: PREVIEW_READY_MESSAGE}, window.location.origin);
        return () => window.removeEventListener('message', onMessage);
    }, [isPreview]);

    const hasSettings = settings !== null;
    const previewLocale = settings?.locale;

    useEffect(() => {
        if (!isPreview || !hasSettings) {
            return;
        }
        dynamicActivateLocale(previewLocale ?? getSupportedLocale(window.navigator.language))
            .catch(() => undefined);
    }, [isPreview, hasSettings, previewLocale]);

    return settings;
};

export const useWidgetRuntime = () => {
    const location = useLocation();
    const loaderData = useLoaderData() as WidgetLoaderData | undefined;
    const search = useMemo(() => new URLSearchParams(location.search), [location.search]);
    const isPreview = search.get(WIDGET_PARAM.preview) === 'true';
    const previewSettings = usePreviewSettings(isPreview);
    const preset = loaderData?.preset ?? null;
    const config: WidgetRuntimeConfig = useMemo(() => previewSettings
        ? resolveWidgetConfig(new URLSearchParams({[WIDGET_PARAM.mode]: search.get(WIDGET_PARAM.mode) ?? ''}), previewSettings)
        : resolveWidgetConfig(search, preset), [search, preset, previewSettings]);

    const checkoutParams = useMemo(() => ({
        ...embedLocaleParam(embedLocaleForPath(location.pathname, location.search, preset?.locale)),
        ...forwardedTrackingParams(search),
        ...config.prefill,
    }), [location.pathname, location.search, search, preset?.locale, config.prefill]);

    return {
        config,
        search,
        checkoutParams,
        promoCodeValid: loaderData?.promoCodeValid,
        notFound: !!loaderData?.notFound,
        isPreview,
    };
};

export interface WidgetAppearance {
    colors: WidgetColors;
    style: ReturnType<typeof widgetFrameStyle>;
    mode: 'light' | 'dark';
}

export const useWidgetAppearance = (
    config: WidgetRuntimeConfig,
    theme?: Partial<HomepageThemeSettings> | null,
): WidgetAppearance => {
    const colors = useMemo(() => resolveWidgetColors(config, theme), [config, theme]);
    const font = resolveWidgetFont(config, theme);

    useEffect(() => {
        if (font) {
            ensureHomepageFontLoaded(font);
        }
    }, [font]);

    return useMemo(() => ({
        colors,
        style: widgetFrameStyle(colors, config, font),
        mode: widgetColorMode(colors),
    }), [colors, config, font]);
};
