import {getConfig} from "./config.ts";
import {PREVIOUS_URL_KEY} from "../api/client.ts";

const normalizeBaseUrl = (url?: string) => {
    if (!url) {
        return '';
    }

    return url.endsWith('/') ? url.slice(0, -1) : url;
};

export const startOidcLogin = (returnTo?: string) => {
    if (typeof window === 'undefined') {
        return;
    }

    const target = returnTo || window.location.href;
    window.localStorage?.setItem(PREVIOUS_URL_KEY, target);

    const apiBase = normalizeBaseUrl(getConfig('VITE_API_URL_CLIENT'));
    const redirectUrl = `${apiBase || ''}/auth/oidc/redirect?return_to=${encodeURIComponent(target)}`;

    window.location.href = redirectUrl;
};
