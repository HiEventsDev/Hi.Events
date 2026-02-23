export const removeTransparency = (color: string): string => {
    color = color.trim();

    // Handle rgba() format
    const rgbaMatch = color.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(\d*\.?\d+))?\s*\)$/i);
    if (rgbaMatch) {
        const [_, r, g, b, a] = rgbaMatch;
        if (a !== undefined && parseFloat(a) < 1) {
            return `rgb(${r}, ${g}, ${b})`;
        }
        return color; // Already opaque
    }

    // Handle 8-digit hex (#RRGGBBAA)
    const hex8Match = color.match(/^#([0-9a-f]{8})$/i);
    if (hex8Match) {
        const [_, hex] = hex8Match;
        const alpha = parseInt(hex.slice(6, 8), 16);
        if (alpha < 255) {
            return `#${hex.slice(0, 6)}`;
        }
        return color; // Already opaque
    }

    // Handle 4-digit hex (#RGBA)
    const hex4Match = color.match(/^#([0-9a-f]{4})$/i);
    if (hex4Match) {
        const [_, hex] = hex4Match;
        const alpha = parseInt(hex[3] + hex[3], 16);
        if (alpha < 255) {
            return `#${hex.slice(0, 3)}`;
        }
        return color;
    }

    return color;
};

/**
 * Calculates the relative luminance of a color.
 * Supports HEX and basic rgb/rgba strings.
 */
export const getLuminance = (color: string): number => {
    let r = 0, g = 0, b = 0;

    color = color.trim().toLowerCase();

    if (color.startsWith('#')) {
        let hex = color.slice(1);
        if (hex.length === 3 || hex.length === 4) {
            r = parseInt(hex[0] + hex[0], 16);
            g = parseInt(hex[1] + hex[1], 16);
            b = parseInt(hex[2] + hex[2], 16);
        } else if (hex.length === 6 || hex.length === 8) {
            r = parseInt(hex.slice(0, 2), 16);
            g = parseInt(hex.slice(2, 4), 16);
            b = parseInt(hex.slice(4, 6), 16);
        }
    } else if (color.startsWith('rgb')) {
        const match = color.match(/\d+/g);
        if (match && match.length >= 3) {
            r = parseInt(match[0], 10);
            g = parseInt(match[1], 10);
            b = parseInt(match[2], 10);
        }
    }

    const [rs, gs, bs] = [r / 255, g / 255, b / 255].map(c =>
        c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4)
    );

    return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
};

/**
 * Returns true if the color is perceived as dark (requires white text).
 */
export const isColorDark = (color: string): boolean => {
    return getLuminance(color) < 0.5;
};

