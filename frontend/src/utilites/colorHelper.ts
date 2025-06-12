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
