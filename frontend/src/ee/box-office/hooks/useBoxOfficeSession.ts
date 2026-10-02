import {useCallback, useEffect, useState} from "react";
import {BoxOfficeSession} from "../../../types.ts";
import {isSsr} from "../../../utilites/helpers.ts";
import {
    BOX_OFFICE_SESSION_EXPIRED_EVENT,
    clearBoxOfficeSession,
    getStoredBoxOfficeSession,
    storeBoxOfficeSession,
} from "../utilites/boxOfficeSession.ts";

type SessionState =
    | { status: 'loading'; session: null }
    | { status: 'none'; session: null; expired: boolean; previous: BoxOfficeSession | null }
    | { status: 'active'; session: BoxOfficeSession };

export const useBoxOfficeSession = (boxOfficeShortId: string | undefined) => {
    const [state, setState] = useState<SessionState>({status: 'loading', session: null});

    useEffect(() => {
        if (isSsr() || !boxOfficeShortId) return;
        const stored = getStoredBoxOfficeSession(boxOfficeShortId);
        setState(stored ? {status: 'active', session: stored} : {status: 'none', session: null, expired: false, previous: null});
    }, [boxOfficeShortId]);

    const clear = useCallback((expired = false) => {
        if (boxOfficeShortId) clearBoxOfficeSession(boxOfficeShortId);
        setState(current => ({
            status: 'none',
            session: null,
            expired,
            previous: expired ? current.session ?? (current.status === 'none' ? current.previous : null) : null,
        }));
    }, [boxOfficeShortId]);

    useEffect(() => {
        if (isSsr()) return;
        const handleExpired = () => clear(true);
        window.addEventListener(BOX_OFFICE_SESSION_EXPIRED_EVENT, handleExpired);
        return () => window.removeEventListener(BOX_OFFICE_SESSION_EXPIRED_EVENT, handleExpired);
    }, [clear]);

    const start = useCallback((session: BoxOfficeSession) => {
        if (boxOfficeShortId) storeBoxOfficeSession(boxOfficeShortId, session);
        setState({status: 'active', session});
    }, [boxOfficeShortId]);

    return {...state, start, update: start, clear};
};
