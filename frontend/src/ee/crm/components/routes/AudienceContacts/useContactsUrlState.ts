import {useEffect, useState} from "react";
import {useSearchParams} from "react-router";
import {useFilterQueryParamSync} from "../../../../../hooks/useFilterQueryParamSync.ts";
import {QueryFilters} from "../../../../../types.ts";
import {CrmSegmentDefinition, CrmSegmentReference, CrmSuppressionReason} from "../../../types.ts";
import {parseDefinitionParam, parseSegmentParam, selectionToReference} from "../../../utilites/format.ts";
import {SUPPRESSION_REASONS} from "../../../utilites/suppression.ts";
import {isRuleComplete} from "../../SegmentBuilder";
import {SortDirection} from "./ContactStack.tsx";

export const SUPPRESSED_SCOPE = 'suppressed';

type ContactListParams = Partial<QueryFilters> & {
    segment?: string;
    filter?: string;
    reason?: string;
};

const appliedRules = (definition: CrmSegmentDefinition | null): CrmSegmentDefinition | null => {
    const rules = definition?.rules.filter(isRuleComplete) ?? [];

    return definition && rules.length > 0 ? {...definition, rules} : null;
};

const suppressedSegment = (reason: CrmSuppressionReason | null): CrmSegmentReference => ({
    definition: {
        match: 'all',
        rules: [{field: 'email_marketing_status', operator: 'in', value: reason ? [reason] : [...SUPPRESSION_REASONS]}],
    },
});

export const useContactsUrlState = () => {
    const [params, updateParams] = useFilterQueryParamSync<ContactListParams>();
    const [searchParams, setSearchParams] = useSearchParams();

    const isSuppressed = params.segment === SUPPRESSED_SCOPE;
    const query = params.query ?? '';
    const page = Math.max(Number(params.pageNumber) || 1, 1);
    const sortBy = params.sortBy ?? (isSuppressed ? 'email_marketing_status_changed_at' : 'last_activity_at');
    const sortDirection: SortDirection = params.sortDirection === 'asc' ? 'asc' : 'desc';
    const reason = isSuppressed ? SUPPRESSION_REASONS.find(value => value === params.reason) ?? null : null;
    const selection = parseSegmentParam(isSuppressed ? null : params.segment ?? null);
    const filter = isSuppressed ? null : appliedRules(parseDefinitionParam(params.filter));
    const segmentReference = isSuppressed
        ? suppressedSegment(reason)
        : filter ? {definition: filter} : selectionToReference(selection);
    const contactId = searchParams.get('contact');
    const listKey = JSON.stringify([query, params.filter ?? null, params.segment ?? null, reason]);

    const [draft, setDraft] = useState<CrmSegmentDefinition | null>(filter);
    const appliedKey = JSON.stringify(filter);

    useEffect(() => {
        setDraft(current => current === null || JSON.stringify(appliedRules(current)) === appliedKey ? current : filter);
    }, [appliedKey]);

    const setContactParam = (id: number | null) => setSearchParams(previous => {
        const next = new URLSearchParams(previous);
        if (id === null) {
            next.delete('contact');
        } else {
            next.set('contact', String(id));
        }
        return next;
    }, {replace: true});

    const changeFilter = (next: CrmSegmentDefinition) => {
        setDraft(next);
        const applied = appliedRules(next);
        updateParams({filter: applied ? JSON.stringify(applied) : null, segment: null, pageNumber: null});
    };

    const clearFilter = () => {
        setDraft(null);
        updateParams({filter: null, pageNumber: null});
    };

    const selectScope = (value: string | null) => {
        setDraft(null);
        updateParams({segment: !value || value === 'all' ? null : value, filter: null, reason: null, sortBy: null, sortDirection: null, pageNumber: null});
    };

    const selectSavedSegment = (segmentId: number) => {
        setDraft(null);
        updateParams({segment: `saved:${segmentId}`, filter: null, pageNumber: null});
    };

    return {
        isSuppressed,
        query,
        page,
        sortBy,
        sortDirection,
        reason,
        selection,
        filter,
        segmentReference,
        contactId,
        listKey,
        draft,
        setDraft,
        setQuery: (value: string | null) => updateParams({query: value, pageNumber: null}),
        setReason: (value: string | null) => updateParams({reason: value, pageNumber: null}),
        setSort: (nextSortBy: string, nextDirection: SortDirection) => updateParams({sortBy: nextSortBy, sortDirection: nextDirection, pageNumber: null}),
        setPage: (value: number) => updateParams({pageNumber: value === 1 ? null : value}),
        openContact: (id: number) => setContactParam(id),
        closeContact: () => setContactParam(null),
        changeFilter,
        clearFilter,
        selectScope,
        selectSavedSegment,
    };
};
