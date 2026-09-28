import {useCallback, useEffect, useState} from "react";

const storageKey = (salesPointShortId: string) => `cashless-pos-session-${salesPointShortId}`;

const readFromSession = (key: string): string | null => {
    try {
        return typeof window === 'undefined' ? null : window.sessionStorage.getItem(key);
    } catch {
        return null;
    }
};

const writeToSession = (key: string, value: string | null): void => {
    try {
        if (value === null) {
            window.sessionStorage.removeItem(key);
            return;
        }
        window.sessionStorage.setItem(key, value);
    } catch {
        return;
    }
};

export const usePosSession = (salesPointShortId: string) => {
    const [token, setToken] = useState<string | null>(null);

    useEffect(() => {
        setToken(readFromSession(storageKey(salesPointShortId)));
    }, [salesPointShortId]);

    const storeToken = useCallback((newToken: string) => {
        writeToSession(storageKey(salesPointShortId), newToken);
        setToken(newToken);
    }, [salesPointShortId]);

    const clearToken = useCallback(() => {
        writeToSession(storageKey(salesPointShortId), null);
        setToken(null);
    }, [salesPointShortId]);

    return {token, storeToken, clearToken};
};
