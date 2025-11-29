import {HomepageThemeSettings} from "../types.ts";

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
}

export function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
    if (!hex || typeof hex !== 'string') {
        return null;
    }

    // Handle rgb/rgba format
    const rgbMatch = hex.match(/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    if (rgbMatch) {
        return {
            r: parseInt(rgbMatch[1], 10),
            g: parseInt(rgbMatch[2], 10),
            b: parseInt(rgbMatch[3], 10),
        };
    }

    const cleanHex = hex.replace(/^#/, '').trim();

    // Handle 3-digit hex
    let fullHex = cleanHex;
    if (cleanHex.length === 3) {
        fullHex = cleanHex[0] + cleanHex[0] + cleanHex[1] + cleanHex[1] + cleanHex[2] + cleanHex[2];
    }

    // Handle 4-digit hex (with alpha)
    if (cleanHex.length === 4) {
        fullHex = cleanHex[0] + cleanHex[0] + cleanHex[1] + cleanHex[1] + cleanHex[2] + cleanHex[2];
    }

    // Handle 8-digit hex (with alpha) - just take the first 6
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
        return 128; // Default to middle value
    }
    return 0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b;
}

export function detectMode(backgroundColor: string): 'light' | 'dark' {
    const luminance = calculateLuminance(backgroundColor);
    return luminance > 128 ? 'light' : 'dark';
}

export function isLightColor(hex: string): boolean {
    return calculateLuminance(hex) > 128;
}

export function getContrastColor(backgroundColor: string): string {
    return isLightColor(backgroundColor) ? '#1a1a1a' : '#ffffff';
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
        textTertiary: '#737373',
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
    };
}

export function getDefaultThemeSettings(): HomepageThemeSettings {
    return {
        accent: '#8b5cf6',
        background: '#f5f3ff',
        mode: 'light',
        background_type: 'COLOR',
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
    };
}

/**
 * Calculate relative luminance for WCAG contrast ratio
 * Uses sRGB to linear conversion per WCAG 2.1 specification
 */
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

/**
 * Calculate WCAG contrast ratio between two colors
 * Returns a value between 1 and 21
 */
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

/**
 * Check if a color combination meets WCAG accessibility standards
 * AA requires 4.5:1 for normal text, 3:1 for large text
 * AAA requires 7:1 for normal text, 4.5:1 for large text
 */
export function checkContrast(foreground: string, background: string): ContrastResult {
    const ratio = getContrastRatio(foreground, background);

    return {
        ratio: Math.round(ratio * 100) / 100,
        passesAA: ratio >= 4.5,
        passesAAA: ratio >= 7,
        level: ratio >= 7 ? 'AAA' : ratio >= 4.5 ? 'AA' : 'fail',
    };
}

/**
 * Check if theme settings have any contrast issues
 * Returns true if there are readability problems
 */
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
