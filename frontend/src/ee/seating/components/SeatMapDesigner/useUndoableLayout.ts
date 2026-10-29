import {useCallback, useState} from "react";
import {SeatMapLayout} from "../lib/types.ts";

const HISTORY_LIMIT = 100;

interface History {
    past: SeatMapLayout[];
    present: SeatMapLayout;
    future: SeatMapLayout[];
    saved: SeatMapLayout;
}

export const useUndoableLayout = (initial: SeatMapLayout) => {
    const [history, setHistory] = useState<History>({past: [], present: initial, future: [], saved: initial});

    const preview = useCallback((layout: SeatMapLayout) => {
        setHistory(current => ({...current, present: layout}));
    }, []);

    const commit = useCallback((before: SeatMapLayout) => {
        setHistory(current => current.present === before
            ? current
            : {...current, past: [...current.past, before].slice(-HISTORY_LIMIT), future: []});
    }, []);

    const apply = useCallback((change: (layout: SeatMapLayout) => SeatMapLayout) => {
        setHistory(current => {
            const next = change(current.present);
            return next === current.present
                ? current
                : {...current, past: [...current.past, current.present].slice(-HISTORY_LIMIT), present: next, future: []};
        });
    }, []);

    const undo = useCallback(() => {
        setHistory(current => current.past.length === 0 ? current : {
            ...current,
            past: current.past.slice(0, -1),
            present: current.past[current.past.length - 1],
            future: [...current.future, current.present],
        });
    }, []);

    const redo = useCallback(() => {
        setHistory(current => current.future.length === 0 ? current : {
            ...current,
            past: [...current.past, current.present],
            present: current.future[current.future.length - 1],
            future: current.future.slice(0, -1),
        });
    }, []);

    const markSaved = useCallback((layout: SeatMapLayout) => {
        setHistory(current => ({...current, saved: layout}));
    }, []);

    return {
        layout: history.present,
        isDirty: history.present !== history.saved,
        canUndo: history.past.length > 0,
        canRedo: history.future.length > 0,
        preview,
        commit,
        apply,
        undo,
        redo,
        markSaved,
    };
};
