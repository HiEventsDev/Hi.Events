import {isSsr, safeSessionStorageGet, safeSessionStorageSet} from "./helpers.ts";

const SESSION_KEY_PREFIX = 'hievents.checkout.session.';
const COMPLETION_REPORTED_KEY_PREFIX = 'hievents.checkout.completionReported.';

const readSessionIdentifierFromUrl = (): string | null => {
    if (isSsr()) return null;
    try {
        return new URL(window.location.href).searchParams.get('session_identifier');
    } catch {
        return null;
    }
};

export const setCheckoutSessionIdentifier = (orderShortId: string, token: string): void => {
    safeSessionStorageSet(SESSION_KEY_PREFIX + orderShortId, token);
};

export const getCheckoutSessionIdentifier = (orderShortId: string): string | null => {
    const fromUrl = readSessionIdentifierFromUrl();
    if (fromUrl) {
        setCheckoutSessionIdentifier(orderShortId, fromUrl);
        return fromUrl;
    }

    return safeSessionStorageGet(SESSION_KEY_PREFIX + orderShortId);
};

export const claimOrderCompletionReport = (orderShortId: string): boolean => {
    if (safeSessionStorageGet(COMPLETION_REPORTED_KEY_PREFIX + orderShortId)) {
        return false;
    }
    safeSessionStorageSet(COMPLETION_REPORTED_KEY_PREFIX + orderShortId, '1');
    return true;
};
