export const SELF_SERVICE_TOKEN_HEADER = 'X-Self-Service-Token';

export interface SelfServiceLink {
    eventId: string | number;
    orderShortId?: string;
    attendeeShortId?: string;
}

interface StoredSession {
    token: string;
    expires_at: string;
}

const LOOKUP_SESSION_KEY = 'hi_self_service_lookup';

const storage = (): Storage | null => {
    try {
        return typeof window === 'undefined' ? null : window.sessionStorage;
    } catch {
        return null;
    }
};

const linkKey = (link: SelfServiceLink): string =>
    `hi_self_service_${link.eventId}_${link.attendeeShortId ? `a_${link.attendeeShortId}` : `o_${link.orderShortId}`}`;

const read = (key: string): string | null => {
    const raw = storage()?.getItem(key);
    if (!raw) {
        return null;
    }

    try {
        const session = JSON.parse(raw) as StoredSession;

        return new Date(session.expires_at).getTime() > Date.now() ? session.token : null;
    } catch {
        return null;
    }
};

const write = (key: string, session: StoredSession): void => {
    try {
        storage()?.setItem(key, JSON.stringify({token: session.token, expires_at: session.expires_at}));
    } catch {
        return;
    }
};

export const getSelfServiceToken = (link: SelfServiceLink): string | null =>
    read(linkKey(link)) ?? read(LOOKUP_SESSION_KEY);

export const storeSelfServiceSession = (link: SelfServiceLink, session: StoredSession): void =>
    write(linkKey(link), session);

export const storeLookupSession = (session: StoredSession): void => write(LOOKUP_SESSION_KEY, session);

export const clearSelfServiceSession = (link: SelfServiceLink): void => {
    try {
        storage()?.removeItem(linkKey(link));
        storage()?.removeItem(LOOKUP_SESSION_KEY);
    } catch {
        return;
    }
};

export const selfServiceHeaders = (link: SelfServiceLink): Record<string, string> => {
    const token = getSelfServiceToken(link);

    return token ? {[SELF_SERVICE_TOKEN_HEADER]: token} : {};
};
