import {useEffect, useRef, useState} from "react";
import {t} from "@lingui/macro";
import {showError, showInfo} from "../../../../../utilites/notifications.tsx";
import {CrmBulkSelection, CrmSegmentReference} from "../../../types.ts";
import {formatCount} from "../../../utilites/format.ts";

const MAX_SELECTED = 1000;

interface ContactSelectionOptions {
    listKey: string;
    query: string;
    segmentReference: CrmSegmentReference | null | undefined;
    total: number;
    pageIds: string[];
}

export const useContactSelection = ({listKey, query, segmentReference, total, pageIds}: ContactSelectionOptions) => {
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
    const [allMatching, setAllMatching] = useState(false);
    const previousListKey = useRef(listKey);

    const clear = () => {
        setSelectedIds(new Set());
        setAllMatching(false);
    };

    useEffect(() => {
        if (previousListKey.current === listKey) {
            return;
        }
        previousListKey.current = listKey;
        if (selectedIds.size > 0 || allMatching) {
            clear();
            showInfo(t`Selection cleared because the list changed`);
        }
    }, [listKey]);

    const change = (next: Set<string>) => {
        if (next.size > MAX_SELECTED) {
            const limit = formatCount(MAX_SELECTED);
            showError(t`You can pick up to ${limit} contacts one at a time. To select more, select the whole page, then choose to select all matching contacts.`);
            return;
        }
        setAllMatching(false);
        setSelectedIds(next);
    };

    const bulkSelection: CrmBulkSelection | null = allMatching
        ? {mode: 'filter', query, segment: segmentReference ?? {}}
        : selectedIds.size > 0 ? {mode: 'ids', contactIds: [...selectedIds].map(Number)} : null;

    return {
        selectedIds,
        visibleSelectedIds: allMatching ? new Set(pageIds) : selectedIds,
        allMatching,
        count: allMatching ? total : selectedIds.size,
        isPageSelected: pageIds.length > 0 && (allMatching || pageIds.every(id => selectedIds.has(id))),
        bulkSelection,
        selectAllMatching: () => setAllMatching(true),
        change,
        clear,
    };
};
