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

export const savedSettings = (widget: Widget): WidgetSettings => widget.type === 'TICKETS' || widget.type === 'BUTTON'
    ? {...widget.settings, sections: widget.settings.sections ?? ['tickets']}
    : {...widget.settings};

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
