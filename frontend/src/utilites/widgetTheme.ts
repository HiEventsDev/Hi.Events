import {CSSProperties} from "react";
import {HomepageThemeSettings} from "../types.ts";
import {computeThemeVariables, detectMode, validateThemeSettings} from "./themeUtils.ts";
import {buildHomepageFontStack} from "../constants/homepageFonts.ts";
import {WidgetSettings} from "./widgetConfig.ts";

export interface WidgetColors {
    background: string;
    primary: string;
    primaryText: string;
    secondary: string;
    secondaryText: string;
    bodyBackground: string;
}

const DEFAULT_COLORS: WidgetColors = {
    background: '#ffffff',
    primary: '#7b5db8',
    primaryText: '#000000',
    secondary: '#7b5eb9',
    secondaryText: '#ffffff',
    bodyBackground: '#ffffff',
};

const DEFAULT_PADDING = 10;

const usesEventTheme = (settings: WidgetSettings, theme?: Partial<HomepageThemeSettings> | null) =>
    settings.theme === 'event' && !!theme;

export const resolveWidgetColors = (settings: WidgetSettings, theme?: Partial<HomepageThemeSettings> | null): WidgetColors => {
    const base = usesEventTheme(settings, theme) ? (() => {
        const resolved = validateThemeSettings(theme);
        const vars = computeThemeVariables(resolved);
        return {
            background: vars['--theme-surface'],
            primary: resolved.accent,
            primaryText: vars['--theme-text-primary'],
            secondary: resolved.accent,
            secondaryText: vars['--theme-accent-contrast'],
            bodyBackground: vars['--theme-surface'],
        };
    })() : DEFAULT_COLORS;

    const background = settings.background_color || base.background;

    return {
        background,
        primary: settings.primary_color || base.primary,
        primaryText: settings.primary_text_color || base.primaryText,
        secondary: settings.secondary_color || base.secondary,
        secondaryText: settings.secondary_text_color || base.secondaryText,
        bodyBackground: background,
    };
};

export const resolveWidgetFont = (settings: WidgetSettings, theme?: Partial<HomepageThemeSettings> | null): string | null => {
    if (settings.font) {
        return settings.font;
    }
    return usesEventTheme(settings, theme) ? validateThemeSettings(theme).font_family ?? null : null;
};

const widgetPadding = (settings: WidgetSettings) =>
    `${Number.isFinite(settings.padding) ? settings.padding : DEFAULT_PADDING}px`;

// eslint-disable-next-line lingui/no-unlocalized-strings
const mix = (color: string, percent: number, base = 'transparent') => `color-mix(in srgb, ${color} ${percent}%, ${base})`;

export const widgetFrameStyle = (colors: WidgetColors, settings: WidgetSettings, font: string | null): CSSProperties => {
    const textSecondary = mix(colors.primaryText, 68);
    const textTertiary = mix(colors.primaryText, 62);
    const border = mix(colors.primary, 24);
    const accentSoft = mix(colors.secondary, 10);
    const accentMuted = mix(colors.secondary, 60);
    const fontStack = font ? buildHomepageFontStack(font) : undefined;

    return {
        '--widget-background-color': colors.background,
        '--widget-primary-color': colors.primary,
        '--widget-primary-text-color': colors.primaryText,
        '--widget-secondary-color': colors.secondary,
        '--widget-secondary-text-color': colors.secondaryText,
        '--widget-padding': widgetPadding(settings),
        ...(Number.isFinite(settings.radius) ? {'--widget-radius': `${settings.radius}px`} : {}),
        ...(fontStack ? {
            '--widget-font-family': fontStack,
            '--theme-font-family': fontStack,
            '--mantine-font-family': fontStack,
            '--mantine-font-family-headings': fontStack,
            fontFamily: fontStack,
        } : {}),
        '--event-bg-color': colors.background,
        '--event-content-bg-color': colors.background,
        '--event-primary-color': colors.secondary,
        '--event-primary-text-color': colors.primaryText,
        '--event-secondary-color': textSecondary,
        '--event-secondary-text-color': textTertiary,
        '--event-accent-contrast': colors.secondaryText,
        '--event-accent-soft': accentSoft,
        '--event-accent-muted': accentMuted,
        '--event-border-color': border,
        '--primary-color': colors.secondary,
        '--primary-text-color': colors.primaryText,
        '--secondary-color': textSecondary,
        '--secondary-text-color': textTertiary,
        '--bg-color': colors.background,
        '--content-bg-color': colors.background,
        '--accent-contrast': colors.secondaryText,
        '--accent-soft': accentSoft,
        '--accent-muted': accentMuted,
        '--border-color': border,
        backgroundColor: colors.background,
        color: colors.primaryText,
    } as CSSProperties;
};

export const widgetColorMode = (colors: WidgetColors): 'light' | 'dark' => {
    try {
        return detectMode(colors.background);
    } catch {
        return 'light';
    }
};
