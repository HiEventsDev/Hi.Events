import {useCallback, useRef, useState} from "react";

/**
 * Hook for table row selection with shift-click range support.
 *
 * Pass the current list of row ids in page order (via `idsInOrder`). The returned
 * `toggle(id, shiftKey)` selects the row; when shiftKey is true and a previous
 * anchor exists, selects/deselects every id between the anchor and the clicked row.
 *
 * Also returns a `shiftKeyRef` and `captureShift` helper so components can stash
 * the shift-state on mousedown (fires before onChange) and read it when Mantine's
 * controlled checkbox onChange fires.
 */
export const useRowSelection = <Id extends number | string>(idsInOrder: Id[]) => {
    const [selected, setSelected] = useState<Set<Id>>(new Set());
    const anchorRef = useRef<Id | null>(null);
    const shiftKeyRef = useRef(false);
    const idsInOrderRef = useRef(idsInOrder);
    idsInOrderRef.current = idsInOrder;

    const toggle = useCallback((id: Id, shiftKey: boolean) => {
        const anchor = anchorRef.current;
        const orderedIds = idsInOrderRef.current;
        setSelected((prev) => {
            const next = new Set(prev);
            if (shiftKey && anchor !== null && anchor !== id) {
                const anchorIdx = orderedIds.indexOf(anchor);
                const targetIdx = orderedIds.indexOf(id);
                if (anchorIdx !== -1 && targetIdx !== -1) {
                    const [from, to] = anchorIdx < targetIdx ? [anchorIdx, targetIdx] : [targetIdx, anchorIdx];
                    const shouldSelect = !prev.has(id);
                    for (let i = from; i <= to; i++) {
                        const rowId = orderedIds[i];
                        if (shouldSelect) next.add(rowId);
                        else next.delete(rowId);
                    }
                    return next;
                }
            }
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
        anchorRef.current = id;
    }, []);

    const captureShift = useCallback((shiftKey: boolean) => {
        shiftKeyRef.current = shiftKey;
    }, []);

    const toggleWithStoredShift = useCallback((id: Id) => {
        toggle(id, shiftKeyRef.current);
        shiftKeyRef.current = false;
    }, [toggle]);

    const clear = useCallback(() => {
        setSelected(new Set());
        anchorRef.current = null;
    }, []);

    const selectAll = useCallback(() => {
        setSelected(new Set(idsInOrderRef.current));
    }, []);

    const isSelected = useCallback((id: Id) => selected.has(id), [selected]);

    return {
        selected,
        selectedIds: Array.from(selected),
        count: selected.size,
        toggle,
        toggleWithStoredShift,
        captureShift,
        clear,
        selectAll,
        isSelected,
    };
};
