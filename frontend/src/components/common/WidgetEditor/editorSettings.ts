import {t} from "@lingui/macro";
import {Widget} from "../../../api/widget.client.ts";
import {WidgetSettings, WidgetType} from "../../../utilites/widgetConfig.ts";

export const COLOR_REGEX = /^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;
export const COLOR_KEYS = ['secondary_color', 'secondary_text_color', 'background_color', 'primary_text_color', 'primary_color'] as const;
const SHARED_KEYS: (keyof WidgetSettings)[] = [
    'theme', ...COLOR_KEYS, 'font', 'radius', 'padding', 'locale', 'checkout', 'continue_button_text', 'autoresize',
    'occurrence_id', 'products', 'categories', 'promo_code', 'hide_promo_code', 'hide_category_titles', 'show_lineup',
];

export const defaultSettings = (type: WidgetType): WidgetSettings => {
    switch (type) {
        case 'BUTTON':
            return {theme: 'event', sections: ['header', 'tickets'], button_text: t`Get tickets`};
        case 'EVENTS':
            return {theme: 'event', layout: 'list', limit: 12, open_in: 'popup'};
        case 'TICKETS':
            return {theme: 'event', sections: ['tickets']};
        default:
            return {theme: 'event'};
    }
};

export interface SelectableIds {
    products?: number[];
    categories?: number[];
    occurrences?: number[];
}

const onlySelectable = (ids: number[] | undefined, selectable: number[] | undefined) =>
    ids && selectable ? ids.filter(id => selectable.includes(id)) : ids;

export const savedSettings = (widget: Widget, selectable: SelectableIds = {}): WidgetSettings => {
    const settings: WidgetSettings = {
        ...widget.settings,
        products: onlySelectable(widget.settings.products, selectable.products),
        categories: onlySelectable(widget.settings.categories, selectable.categories),
    };
    if (settings.occurrence_id && selectable.occurrences && !selectable.occurrences.includes(settings.occurrence_id)) {
        delete settings.occurrence_id;
    }
    return widget.type === 'TICKETS' || widget.type === 'BUTTON'
        ? {...settings, sections: settings.sections ?? ['tickets']}
        : settings;
};

const pick = (settings: WidgetSettings, keys: (keyof WidgetSettings)[]): WidgetSettings => {
    const result: WidgetSettings = {};
    keys.forEach((key) => {
        if (settings[key] !== undefined) {
            (result as Record<string, unknown>)[key] = settings[key];
        }
    });
    return result;
};

export const settingsForNewType = (type: WidgetType, previous: WidgetSettings): WidgetSettings =>
    ({...defaultSettings(type), ...pick(previous, SHARED_KEYS)});
