import {isSsr, safeSessionStorageGet, safeSessionStorageSet} from "./helpers.ts";

const IFRAME_ID_KEY = 'hievents.checkout.iframeId';
const PARENT_URL_KEY = 'hievents.checkout.parentUrl';
const EMBED_MODE_KEY = 'hievents.checkout.embedMode';

let cachedIframeId: string | null = null;
let cachedParentUrl: string | null = null;
let cachedEmbedMode: string | null = null;

const resolveParam = (urlParam: string, storageKey: string, cached: string | null): string | null => {
    if (isSsr()) return null;
    if (cached) return cached;

    const fromUrl = new URLSearchParams(window.location.search).get(urlParam);
    if (fromUrl) {
        safeSessionStorageSet(storageKey, fromUrl);
        return fromUrl;
    }

    return safeSessionStorageGet(storageKey);
};

const getIframeId = (): string | null => {
    cachedIframeId = resolveParam('iframeId', IFRAME_ID_KEY, cachedIframeId);
    return cachedIframeId;
};

export const getEmbedParentUrl = (): string | null => {
    cachedParentUrl = resolveParam('parentUrl', PARENT_URL_KEY, cachedParentUrl);
    return cachedParentUrl;
};

export const getEmbedMode = (): string | null => {
    cachedEmbedMode = resolveParam('embed', EMBED_MODE_KEY, cachedEmbedMode);
    return cachedEmbedMode;
};

export const getParentOrigin = (): string | null => {
    const parentUrl = getEmbedParentUrl();
    if (!parentUrl) return null;
    try {
        return new URL(parentUrl).origin;
    } catch {
        return null;
    }
};

export const sendHeightToParent = (height?: number): void => {
    if (isSsr()) return;

    const iframeId = getIframeId();
    if (!iframeId) return;

    window.parent.postMessage({
        type: 'resize',
        height: height ?? document.documentElement.scrollHeight,
        iframeId,
    }, '*');
};
