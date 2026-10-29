import type {CSSProperties} from "react";
import {HomepageThemeSettings} from "../types.ts";
import {buildHomepageFontStack, DEFAULT_HOMEPAGE_FONT} from "../constants/homepageFonts.ts";

export interface DerivedThemeColors {
    surface: string;
    textPrimary: string;
    textSecondary: string;
    textTertiary: string;
    border: string;
    accentContrast: string;
}

export interface ThemeCSSVariables {
    '--theme-accent': string;
    '--theme-background': string;
    '--theme-surface': string;
    '--theme-text-primary': string;
    '--theme-text-secondary': string;
    '--theme-text-tertiary': string;
    '--theme-border': string;
    '--theme-accent-contrast': string;
    '--theme-accent-soft': string;
    '--theme-accent-muted': string;
    '--theme-accent-tint-10': string;
    '--theme-accent-tint-15': string;
    '--theme-accent-tint-20': string;
    '--theme-font-family': string;
}

export function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
    if (!hex || typeof hex !== 'string') {
        return null;
    }

    const rgbMatch = hex.match(/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    if (rgbMatch) {
        return {
            r: parseInt(rgbMatch[1], 10),
            g: parseInt(rgbMatch[2], 10),
            b: parseInt(rgbMatch[3], 10),
        };
    }

    const cleanHex = hex.replace(/^#/, '').trim();

    let fullHex = cleanHex;
    if (cleanHex.length === 3) {
        fullHex = cleanHex[0] + cleanHex[0] + cleanHex[1] + cleanHex[1] + cleanHex[2] + cleanHex[2];
    }

    if (cleanHex.length === 4) {
        fullHex = cleanHex[0] + cleanHex[0] + cleanHex[1] + cleanHex[1] + cleanHex[2] + cleanHex[2];
    }

    if (fullHex.length === 8) {
        fullHex = fullHex.substring(0, 6);
    }

    if (fullHex.length !== 6) {
        return null;
    }

    const r = parseInt(fullHex.substring(0, 2), 16);
    const g = parseInt(fullHex.substring(2, 4), 16);
    const b = parseInt(fullHex.substring(4, 6), 16);

    if (isNaN(r) || isNaN(g) || isNaN(b)) {
        return null;
    }

    return { r, g, b };
}

export function calculateLuminance(hex: string): number {
    const rgb = hexToRgb(hex);
    if (!rgb) {
        return 128;
    }
    return 0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b;
}

export function detectMode(backgroundColor: string): 'light' | 'dark' {
    const luminance = calculateLuminance(backgroundColor);
    return luminance > 128 ? 'light' : 'dark';
}

const LIGHT_TEXT_COLOR = '#ffffff';
const DARK_TEXT_COLOR = '#1a1a1a';

export function getContrastColor(backgroundColor: string): string {
    if (!hexToRgb(backgroundColor)) {
        return LIGHT_TEXT_COLOR;
    }

    return getContrastRatio(DARK_TEXT_COLOR, backgroundColor) > getContrastRatio(LIGHT_TEXT_COLOR, backgroundColor)
        ? DARK_TEXT_COLOR
        : LIGHT_TEXT_COLOR;
}

export function getDerivedColors(mode: 'light' | 'dark'): Omit<DerivedThemeColors, 'accentContrast'> {
    if (mode === 'light') {
        return {
            surface: '#ffffff',
            textPrimary: '#1a1a1a',
            textSecondary: '#525252',
            textTertiary: '#737373',
            border: 'rgba(0, 0, 0, 0.1)',
        };
    }

    return {
        surface: '#1f1f1f',
        textPrimary: '#ffffff',
        textSecondary: '#a3a3a3',
        textTertiary: '#8f8f8f',
        border: 'rgba(255, 255, 255, 0.1)',
    };
}

export function getAccentSoft(accent: string, mode: 'light' | 'dark'): string {
    const rgb = hexToRgb(accent);
    if (!rgb) {
        return mode === 'light' ? 'rgba(139, 92, 246, 0.08)' : 'rgba(139, 92, 246, 0.15)';
    }
    const opacity = mode === 'light' ? 0.08 : 0.15;
    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${opacity})`;
}

const ACCENT_FILL_WEIGHT = {light: 0.1, dark: 0.16};
const MIN_TEXT_CONTRAST = 4.5;

const mixHex = (a: string, b: string, weight: number): string => {
    const from = hexToRgb(a)!;
    const to = hexToRgb(b)!;
    const channel = (x: number, y: number) => Math.round(x * weight + y * (1 - weight)).toString(16).padStart(2, '0');

    return `#${channel(from.r, to.r)}${channel(from.g, to.g)}${channel(from.b, to.b)}`;
};

export function getAccentInk(accent: string, mode: 'light' | 'dark'): string {
    const derived = getDerivedColors(mode);
    if (!hexToRgb(accent)) {
        return derived.textPrimary;
    }
    const fill = mixHex(accent, derived.surface, ACCENT_FILL_WEIGHT[mode]);

    for (let weight = 1; weight > 0; weight -= 0.05) {
        const candidate = mixHex(accent, derived.textPrimary, weight);
        if (Math.min(getContrastRatio(candidate, fill), getContrastRatio(candidate, derived.surface)) >= MIN_TEXT_CONTRAST) {
            return candidate;
        }
    }

    return derived.textPrimary;
}

export function getAccentSolid(accent: string): string {
    if (!hexToRgb(accent)) {
        return accent;
    }
    const text = getContrastColor(accent);
    const toward = text === LIGHT_TEXT_COLOR ? '#000000' : '#ffffff';

    for (let weight = 1; weight > 0; weight -= 0.05) {
        const candidate = mixHex(accent, toward, weight);
        if (getContrastRatio(candidate, text) >= MIN_TEXT_CONTRAST) {
            return candidate;
        }
    }

    return toward;
}

export function getAccentMuted(accent: string, mode: 'light' | 'dark'): string {
    const rgb = hexToRgb(accent);
    if (!rgb) {
        return mode === 'light' ? 'rgba(139, 92, 246, 0.6)' : 'rgba(139, 92, 246, 0.7)';
    }
    const opacity = mode === 'light' ? 0.6 : 0.7;
    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${opacity})`;
}

export function computeThemeVariables(settings: HomepageThemeSettings): ThemeCSSVariables {
    const derived = getDerivedColors(settings.mode);
    const accentContrast = getContrastColor(settings.accent);
    const accentSoft = getAccentSoft(settings.accent, settings.mode);
    const accentMuted = getAccentMuted(settings.accent, settings.mode);

    return {
        '--theme-accent': settings.accent,
        '--theme-background': settings.background,
        '--theme-surface': derived.surface,
        '--theme-text-primary': derived.textPrimary,
        '--theme-text-secondary': derived.textSecondary,
        '--theme-text-tertiary': derived.textTertiary,
        '--theme-border': derived.border,
        '--theme-accent-contrast': accentContrast,
        '--theme-accent-soft': accentSoft,
        '--theme-accent-muted': accentMuted,
        '--theme-accent-tint-10': `color-mix(in srgb, ${settings.accent} 10%, ${derived.surface})`,
        '--theme-accent-tint-15': `color-mix(in srgb, ${settings.accent} 15%, ${derived.surface})`,
        '--theme-accent-tint-20': `color-mix(in srgb, ${settings.accent} 20%, ${derived.surface})`,
        '--theme-font-family': buildHomepageFontStack(settings.font_family),
    };
}

export function homepageThemeStyle(settings: HomepageThemeSettings): CSSProperties {
    const vars = computeThemeVariables(settings);

    return {
        '--event-bg-color': settings.background,
        '--event-content-bg-color': vars['--theme-surface'],
        '--event-primary-color': settings.accent,
        '--event-primary-text-color': vars['--theme-text-primary'],
        '--event-secondary-color': vars['--theme-text-secondary'],
        '--event-secondary-text-color': vars['--theme-text-tertiary'],
        '--event-accent-contrast': vars['--theme-accent-contrast'],
        '--event-accent-soft': vars['--theme-accent-soft'],
        '--event-accent-muted': vars['--theme-accent-muted'],
        '--event-accent-ink': getAccentInk(settings.accent, settings.mode),
        '--event-accent-solid': getAccentSolid(settings.accent),
        '--event-border-color': vars['--theme-border'],
        '--theme-font-family': vars['--theme-font-family'],
        fontFamily: vars['--theme-font-family'],
    } as CSSProperties;
}

export function getDefaultThemeSettings(): HomepageThemeSettings {
    return {
        accent: '#7c3aed',
        background: '#f5f3ff',
        mode: 'light',
        background_type: 'COLOR',
        font_family: DEFAULT_HOMEPAGE_FONT,
    };
}

export function validateThemeSettings(
    settings: Partial<HomepageThemeSettings> | null | undefined
): HomepageThemeSettings {
    const defaults = getDefaultThemeSettings();

    if (!settings) {
        return defaults;
    }

    return {
        accent: settings.accent || defaults.accent,
        background: settings.background || defaults.background,
        mode: settings.mode || detectMode(settings.background || defaults.background),
        background_type: settings.background_type || defaults.background_type,
        font_family: settings.font_family || defaults.font_family,
        ...(settings.layout ? {layout: settings.layout} : {}),
    };
}

export function getRelativeLuminance(hex: string): number {
    const rgb = hexToRgb(hex);
    if (!rgb) return 0.5;

    const toLinear = (c: number): number => {
        const sRGB = c / 255;
        return sRGB <= 0.03928
            ? sRGB / 12.92
            : Math.pow((sRGB + 0.055) / 1.055, 2.4);
    };

    const r = toLinear(rgb.r);
    const g = toLinear(rgb.g);
    const b = toLinear(rgb.b);

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function getContrastRatio(foreground: string, background: string): number {
    const l1 = getRelativeLuminance(foreground);
    const l2 = getRelativeLuminance(background);

    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);

    return (lighter + 0.05) / (darker + 0.05);
}

export interface ContrastResult {
    ratio: number;
    passesAA: boolean;
    passesAAA: boolean;
    level: 'fail' | 'AA' | 'AAA';
}

export function checkContrast(foreground: string, background: string): ContrastResult {
    const ratio = getContrastRatio(foreground, background);

    return {
        ratio: Math.round(ratio * 100) / 100,
        passesAA: ratio >= 4.5,
        passesAAA: ratio >= 7,
        level: ratio >= 7 ? 'AAA' : ratio >= 4.5 ? 'AA' : 'fail',
    };
}

export function hasContrastIssues(settings: HomepageThemeSettings): boolean {
    const derived = getDerivedColors(settings.mode);

    // Check accent color on surface (buttons)
    const accentContrast = getContrastColor(settings.accent);
    const buttonTextContrast = checkContrast(accentContrast, settings.accent);
    if (!buttonTextContrast.passesAA) {
        return true;
    }

    // Check accent color on surface (links, icons) - needs at least 3:1
    const accentOnSurface = checkContrast(settings.accent, derived.surface);
    if (accentOnSurface.ratio < 3) {
        return true;
    }

    return false;
}
