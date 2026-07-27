import {useCallback, useEffect, useMemo, useState} from "react";
import {useSearchParams} from "react-router";
import {EventOccurrence} from "../types.ts";
import {isSsr} from "../utilites/helpers.ts";

const URL_PARAM = "occurrence";
const STORAGE_KEY_PREFIX = "checkInOccurrenceFilter:";

const storageKey = (checkInListShortId: string | undefined) =>
    checkInListShortId ? `${STORAGE_KEY_PREFIX}${checkInListShortId}` : null;

const readPersisted = (key: string | null): number | null => {
    if (!key || isSsr()) return null;
    const raw = localStorage.getItem(key);
    if (!raw) return null;
    const parsed = Number(raw);
    return Number.isFinite(parsed) ? parsed : null;
};

const writePersisted = (key: string | null, id: number | null): void => {
    if (!key || isSsr()) return;
    if (id === null) {
        localStorage.removeItem(key);
    } else {
        localStorage.setItem(key, String(id));
    }
};

export interface UseCheckInOccurrenceFilterResult {
    occurrenceId: number | null;
    activeOccurrence: EventOccurrence | null;
    setOccurrenceId: (id: number | null) => void;
    clear: () => void;
    didClearStale: boolean;
}

export const useCheckInOccurrenceFilter = (
    checkInListShortId: string | undefined,
    occurrences: EventOccurrence[] | undefined,
): UseCheckInOccurrenceFilterResult => {
    const key = storageKey(checkInListShortId);
    const [searchParams, setSearchParams] = useSearchParams();
    const [didClearStale, setDidClearStale] = useState(false);

    const [hasHydratedFromStorage, setHasHydratedFromStorage] = useState(false);
    useEffect(() => {
        if (hasHydratedFromStorage || !key || isSsr()) return;
        if (!searchParams.has(URL_PARAM)) {
            const persisted = readPersisted(key);
            if (persisted !== null) {
                const next = new URLSearchParams(searchParams);
                next.set(URL_PARAM, String(persisted));
                setSearchParams(next, {replace: true});
            }
        }
        setHasHydratedFromStorage(true);
    }, [key, searchParams, setSearchParams, hasHydratedFromStorage]);

    const rawParam = searchParams.get(URL_PARAM);
    const occurrenceId = useMemo<number | null>(() => {
        if (!rawParam) return null;
        const parsed = Number(rawParam);
        return Number.isFinite(parsed) ? parsed : null;
    }, [rawParam]);

    useEffect(() => {
        if (occurrenceId === null) return;
        if (!occurrences || occurrences.length === 0) return;
        const stillExists = occurrences.some(o => o.id === occurrenceId && o.status !== "CANCELLED");
        if (!stillExists) {
            const next = new URLSearchParams(searchParams);
            next.delete(URL_PARAM);
            setSearchParams(next, {replace: true});
            writePersisted(key, null);
            setDidClearStale(true);
        }
    }, [occurrenceId, occurrences, key, searchParams, setSearchParams]);

    const setOccurrenceId = useCallback((id: number | null) => {
        setDidClearStale(false);
        const next = new URLSearchParams(searchParams);
        if (id === null) {
            next.delete(URL_PARAM);
        } else {
            next.set(URL_PARAM, String(id));
        }
        setSearchParams(next, {replace: false});
        writePersisted(key, id);
    }, [searchParams, setSearchParams, key]);

    const clear = useCallback(() => setOccurrenceId(null), [setOccurrenceId]);

    const activeOccurrence = useMemo(() => {
        if (occurrenceId === null || !occurrences) return null;
        return occurrences.find(o => o.id === occurrenceId) ?? null;
    }, [occurrenceId, occurrences]);

    return {occurrenceId, activeOccurrence, setOccurrenceId, clear, didClearStale};
};
