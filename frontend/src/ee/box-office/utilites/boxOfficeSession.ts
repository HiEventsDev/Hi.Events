import {BoxOfficeSession} from "../../../types.ts";
import {
    isSsr,
    safeLocalStorageGet,
    safeLocalStorageRemove,
    safeLocalStorageSet,
    safeSessionStorageGet,
    safeSessionStorageRemove,
    safeSessionStorageSet,
} from "../../../utilites/helpers.ts";

export const BOX_OFFICE_SESSION_HEADER = 'X-Box-Office-Session';
export const BOX_OFFICE_SESSION_EXPIRED_EVENT = 'box-office-session-expired';
export const BOX_OFFICE_UNAVAILABLE_EVENT = 'box-office-unavailable';

const SESSION_KEY_PREFIX = 'boxOfficeSession:';
const OPERATOR_NAME_KEY = 'boxOfficeOperatorName';
const READER_KEY_PREFIX = 'boxOfficeReader:';
const ACTIVE_SALE_KEY_PREFIX = 'boxOfficeActiveSale:';

const sessionKey = (boxOfficeShortId: string) => SESSION_KEY_PREFIX + boxOfficeShortId;

export const getStoredBoxOfficeSession = (boxOfficeShortId: string): BoxOfficeSession | null => {
    const raw = safeLocalStorageGet(sessionKey(boxOfficeShortId));
    if (!raw) return null;
    try {
        const session = JSON.parse(raw) as BoxOfficeSession;
        if (session.expires_at && new Date(session.expires_at).getTime() < Date.now()) {
            safeLocalStorageRemove(sessionKey(boxOfficeShortId));
            return null;
        }
        return session;
    } catch {
        return null;
    }
};

export const storeBoxOfficeSession = (boxOfficeShortId: string, session: BoxOfficeSession): void => {
    safeLocalStorageSet(sessionKey(boxOfficeShortId), JSON.stringify(session));
    safeLocalStorageSet(OPERATOR_NAME_KEY, session.operator_name);
};

export const clearBoxOfficeSession = (boxOfficeShortId: string): void => {
    safeLocalStorageRemove(sessionKey(boxOfficeShortId));
};

export const getBoxOfficeSessionToken = (boxOfficeShortId: string): string | null =>
    getStoredBoxOfficeSession(boxOfficeShortId)?.token ?? null;

export const getRememberedOperatorName = (): string => safeLocalStorageGet(OPERATOR_NAME_KEY) ?? '';

export const getRememberedReaderId = (boxOfficeShortId: string): string | null =>
    safeLocalStorageGet(READER_KEY_PREFIX + boxOfficeShortId);

export const rememberReaderId = (boxOfficeShortId: string, readerId: string | null): void => {
    if (readerId === null) {
        safeLocalStorageRemove(READER_KEY_PREFIX + boxOfficeShortId);
    } else {
        safeLocalStorageSet(READER_KEY_PREFIX + boxOfficeShortId, readerId);
    }
};

export const getActiveSaleShortId = (boxOfficeShortId: string): string | null =>
    safeSessionStorageGet(ACTIVE_SALE_KEY_PREFIX + boxOfficeShortId);

export const rememberActiveSale = (boxOfficeShortId: string, orderShortId: string | null): void => {
    if (orderShortId === null) {
        safeSessionStorageRemove(ACTIVE_SALE_KEY_PREFIX + boxOfficeShortId);
    } else {
        safeSessionStorageSet(ACTIVE_SALE_KEY_PREFIX + boxOfficeShortId, orderShortId);
    }
};

export const notifyBoxOfficeSessionExpired = (): void => {
    if (isSsr()) return;
    window.dispatchEvent(new Event(BOX_OFFICE_SESSION_EXPIRED_EVENT));
};

export const notifyBoxOfficeUnavailable = (): void => {
    if (isSsr()) return;
    window.dispatchEvent(new Event(BOX_OFFICE_UNAVAILABLE_EVENT));
};
