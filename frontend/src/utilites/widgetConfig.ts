// eslint-disable-next-line lingui/no-unlocalized-strings
export const WIDGET_PARAM = {widget: 'Widget', mode: 'Mode', preview: 'Preview', widgetVersion: 'WidgetVersion', locale: 'Locale'} as const;

export type WidgetType = 'TICKETS' | 'BUTTON' | 'LINEUP' | 'SCHEDULE' | 'EVENTS';

const WIDGET_SECTIONS = ['tickets', 'header', 'about', 'location', 'organizer', 'lineup', 'schedule', 'custom'] as const;
export type WidgetSection = typeof WIDGET_SECTIONS[number];

export type WidgetCheckoutMode = 'modal' | 'new-tab';
export type WidgetTheme = 'custom' | 'event';
export type WidgetEventsLayout = 'list' | 'grid' | 'compact';
export type WidgetOpenIn = 'popup' | 'page';

export interface WidgetSettings {
    background_color?: string;
    primary_color?: string;
    primary_text_color?: string;
    secondary_color?: string;
    secondary_text_color?: string;
    padding?: number;
    radius?: number;
    continue_button_text?: string;
    button_text?: string;
    checkout?: WidgetCheckoutMode;
    autoresize?: boolean;
    show_lineup?: boolean;
    hide_promo_code?: boolean;
    hide_category_titles?: boolean;
    theme?: WidgetTheme;
    font?: string;
    locale?: string;
    products?: number[];
    categories?: number[];
    sections?: WidgetSection[];
    occurrence_id?: number;
    promo_code?: string;
    layout?: WidgetEventsLayout;
    limit?: number;
    event_category?: string;
    show_past?: boolean;
    open_in?: WidgetOpenIn;
}

export interface WidgetRuntimeConfig extends WidgetSettings {
    widgetId: string | null;
    isModal: boolean;
    prefill: Record<string, string>;
}

type SettingKind = 'string' | 'number' | 'boolean' | 'ids' | 'list' | 'padding';

const SETTING_KINDS: Record<keyof WidgetSettings, SettingKind> = {
    background_color: 'string',
    primary_color: 'string',
    primary_text_color: 'string',
    secondary_color: 'string',
    secondary_text_color: 'string',
    padding: 'padding',
    radius: 'number',
    continue_button_text: 'string',
    button_text: 'string',
    checkout: 'string',
    autoresize: 'boolean',
    show_lineup: 'boolean',
    hide_promo_code: 'boolean',
    hide_category_titles: 'boolean',
    theme: 'string',
    font: 'string',
    locale: 'string',
    products: 'ids',
    categories: 'ids',
    sections: 'list',
    occurrence_id: 'number',
    promo_code: 'string',
    layout: 'string',
    limit: 'number',
    event_category: 'string',
    show_past: 'boolean',
    open_in: 'string',
};

const PREFILL_PARAMS: Record<string, string> = {
    PrefillEmail: 'email',
    PrefillFirstName: 'first_name',
    PrefillLastName: 'last_name',
};

const settingParamName = (key: string) => key
    .split('_')
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join('');

export const settingAttributeName = (key: string) => `data-hievents-${key.replace(/_/g, '-')}`;

const parseIds = (value: string): number[] => value
    .split(',')
    .map(part => Number(part.trim()))
    .filter(id => Number.isInteger(id) && id > 0);

const parseValue = (kind: SettingKind, raw: string): unknown => {
    switch (kind) {
        case 'boolean':
            return raw === 'true';
        case 'number': {
            const value = Number(raw);
            return Number.isFinite(value) ? value : undefined;
        }
        case 'padding': {
            const value = parseFloat(raw);
            return Number.isFinite(value) ? value : undefined;
        }
        case 'ids':
            return parseIds(raw);
        case 'list':
            return raw.split(',').map(part => part.trim().toLowerCase()).filter(Boolean);
        default:
            return raw;
    }
};

export const resolveWidgetConfig = (search: URLSearchParams, preset?: WidgetSettings | null): WidgetRuntimeConfig => {
    const resolved: WidgetSettings = {...(preset ?? {})};

    (Object.keys(SETTING_KINDS) as (keyof WidgetSettings)[]).forEach((key) => {
        const raw = search.get(settingParamName(key));
        if (raw === null || raw === '') {
            return;
        }
        const value = parseValue(SETTING_KINDS[key], raw);
        if (value !== undefined) {
            (resolved as Record<string, unknown>)[key] = value;
        }
    });

    const legacyOccurrence = Number(search.get('occurrence_id'));
    if (!resolved.occurrence_id && Number.isInteger(legacyOccurrence) && legacyOccurrence > 0) {
        resolved.occurrence_id = legacyOccurrence;
    }

    const hostPromoCode = search.get('promo_code');
    if (hostPromoCode) {
        resolved.promo_code = hostPromoCode;
    }

    if (resolved.sections) {
        resolved.sections = resolved.sections.filter((section): section is WidgetSection =>
            (WIDGET_SECTIONS as readonly string[]).includes(section));
    }

    const prefill: Record<string, string> = {};
    Object.entries(PREFILL_PARAMS).forEach(([param, key]) => {
        const value = search.get(param);
        if (value) {
            prefill[key] = value;
        }
    });

    return {
        ...resolved,
        widgetId: search.get(WIDGET_PARAM.widget),
        isModal: search.get(WIDGET_PARAM.mode) === 'modal',
        prefill,
    };
};

export interface ProductSelection {
    productIds?: number[];
    categoryIds?: number[];
}

export const productSelectionFromConfig = (config: WidgetSettings): ProductSelection | undefined => {
    const productIds = config.products?.length ? config.products : undefined;
    const categoryIds = config.categories?.length ? config.categories : undefined;
    return productIds || categoryIds ? {productIds, categoryIds} : undefined;
};

const TRACKING_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

const presentParams = (search: URLSearchParams, keys: string[]): Record<string, string> => {
    const params: Record<string, string> = {};
    keys.forEach((key) => {
        const value = search.get(key);
        if (value) {
            params[key] = value;
        }
    });
    return params;
};

export const forwardedTrackingParams = (search: URLSearchParams): Record<string, string> =>
    presentParams(search, TRACKING_PARAMS);

export const forwardedHostParams = (search: URLSearchParams): Record<string, string> =>
    presentParams(search, ['aff', 'promo_code', ...TRACKING_PARAMS]);
