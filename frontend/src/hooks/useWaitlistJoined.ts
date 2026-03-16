import {useState, useCallback, useEffect} from "react";
import {IdParam} from "../types.ts";

const STORAGE_KEY_PREFIX = 'waitlist_joined_';

const getKey = (eventId: IdParam, productPriceId: IdParam) => `${STORAGE_KEY_PREFIX}${eventId}_${productPriceId}`;

export const clearWaitlistJoinedForEvent = (eventId: IdParam) => {
    if (typeof window === 'undefined') return;
    try {
        const prefix = `${STORAGE_KEY_PREFIX}${eventId}_`;
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);
            if (key?.startsWith(prefix)) {
                localStorage.removeItem(key);
            }
        }
    } catch {
        // localStorage unavailable
    }
};

export const useWaitlistJoined = (eventId?: IdParam, productPriceId?: IdParam) => {
    const [joined, setJoined] = useState(false);

    useEffect(() => {
        if (!eventId || !productPriceId) return;
        try {
            setJoined(localStorage.getItem(getKey(eventId, productPriceId)) === '1');
        } catch {
            // localStorage unavailable
        }
    }, [eventId, productPriceId]);

    const markJoined = useCallback(() => {
        if (!eventId || !productPriceId) return;
        setJoined(true);
        try {
            localStorage.setItem(getKey(eventId, productPriceId), '1');
        } catch {
            // localStorage unavailable
        }
    }, [eventId, productPriceId]);

    return {joined, markJoined};
};
