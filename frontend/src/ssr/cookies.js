/**
 * Parse a Cookie header into a key/value map.
 * @param {string | null | undefined} cookieHeader
 * @returns {Record<string, string>}
 */
export function parseCookies(cookieHeader) {
    const cookies = {};
    if (!cookieHeader) {
        return cookies;
    }

    for (const part of cookieHeader.split(";")) {
        const trimmed = part.trim();
        if (!trimmed) {
            continue;
        }
        const eqIndex = trimmed.indexOf("=");
        if (eqIndex === -1) {
            cookies[trimmed] = "";
            continue;
        }
        const key = trimmed.slice(0, eqIndex).trim();
        const value = trimmed.slice(eqIndex + 1).trim();
        try {
            cookies[key] = decodeURIComponent(value);
        } catch {
            cookies[key] = value;
        }
    }

    return cookies;
}
