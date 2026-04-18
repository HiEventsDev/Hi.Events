import {Contact, GenericDataResponse, GenericPaginatedResponse, IdParam, QueryFilters} from "../types.ts";
import {api} from "./client.ts";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const contactClient = {
    create: async (accountId: IdParam, payload: Partial<Contact>) => {
        const response = await api.post<GenericDataResponse<Contact>>(`accounts/${accountId}/contacts`, payload);
        return response.data;
    },
    all: async (accountId: IdParam, params: QueryFilters = {}) => {
        const response = await api.get<GenericPaginatedResponse<Contact>>(
            `accounts/${accountId}/contacts` + queryParamsHelper.buildQueryString(params),
        );
        return response.data;
    },
    get: async (accountId: IdParam, contactId: IdParam) => {
        const response = await api.get<GenericDataResponse<Contact>>(`accounts/${accountId}/contacts/${contactId}`);
        return response.data;
    },
    update: async (accountId: IdParam, contactId: IdParam, payload: Partial<Contact>) => {
        const response = await api.put<GenericDataResponse<Contact>>(
            `accounts/${accountId}/contacts/${contactId}`,
            payload,
        );
        return response.data;
    },
    delete: async (accountId: IdParam, contactId: IdParam) => {
        return await api.delete(`accounts/${accountId}/contacts/${contactId}`);
    },
    backfillSummary: async (accountId: IdParam): Promise<{ data: ContactBackfillSummary }> => {
        const response = await api.get<{ data: ContactBackfillSummary }>(
            `accounts/${accountId}/contacts/backfill/summary`,
        );
        return response.data;
    },
    backfillUnlinkedAttendees: async (
        accountId: IdParam,
        params: QueryFilters = {},
        includeProcessed = false,
    ) => {
        const merged: QueryFilters = {
            ...params,
            additionalParams: {
                ...(params.additionalParams ?? {}),
                ...(includeProcessed ? {include_processed: '1'} : {}),
            },
        };
        const response = await api.get<GenericPaginatedResponse<ContactBackfillUnlinkedAttendee>>(
            `accounts/${accountId}/contacts/backfill/unlinked-attendees` + queryParamsHelper.buildQueryString(merged),
        );
        return response.data;
    },
    backfillIgnoreAttendees: async (accountId: IdParam, attendeeIds: number[]) => {
        const response = await api.post<{ data: { count: number } }>(
            `accounts/${accountId}/contacts/backfill/ignore-attendees`,
            {attendee_ids: attendeeIds},
        );
        return response.data;
    },
    backfillUnignoreAttendees: async (accountId: IdParam, attendeeIds: number[]) => {
        const response = await api.post<{ data: { count: number } }>(
            `accounts/${accountId}/contacts/backfill/unignore-attendees`,
            {attendee_ids: attendeeIds},
        );
        return response.data;
    },
    backfillAddContacts: async (accountId: IdParam, attendeeIds: number[]) => {
        const response = await api.post<{ data: { count: number } }>(
            `accounts/${accountId}/contacts/backfill/add-contacts`,
            {attendee_ids: attendeeIds},
        );
        return response.data;
    },
    backfillReuseQuestions: async (accountId: IdParam, questionIds: number[]) => {
        const response = await api.post<{ data: { count: number } }>(
            `accounts/${accountId}/contacts/backfill/reuse-questions`,
            {question_ids: questionIds},
        );
        return response.data;
    },
    backfillIgnoreQuestions: async (accountId: IdParam, questionIds: number[]) => {
        const response = await api.post<{ data: { count: number } }>(
            `accounts/${accountId}/contacts/backfill/ignore-questions`,
            {question_ids: questionIds},
        );
        return response.data;
    },
    backfillUnignoreQuestions: async (accountId: IdParam, questionIds: number[]) => {
        const response = await api.post<{ data: { count: number } }>(
            `accounts/${accountId}/contacts/backfill/unignore-questions`,
            {question_ids: questionIds},
        );
        return response.data;
    },
    backfillApplyConflictDecisions: async (
        accountId: IdParam,
        decisions: Array<{ question_answer_id: number; decision: 'update' | 'ignore' }>,
    ) => {
        const response = await api.post<{ data: { count: number } }>(
            `accounts/${accountId}/contacts/backfill/apply-conflict-decisions`,
            {decisions},
        );
        return response.data;
    },
    backfillUnmappedQuestions: async (
        accountId: IdParam,
        params: QueryFilters = {},
        includeProcessed = false,
    ) => {
        const merged: QueryFilters = {
            ...params,
            additionalParams: {
                ...(params.additionalParams ?? {}),
                ...(includeProcessed ? {include_processed: '1'} : {}),
            },
        };
        const response = await api.get<GenericPaginatedResponse<ContactBackfillUnmappedQuestion>>(
            `accounts/${accountId}/contacts/backfill/unmapped-questions` + queryParamsHelper.buildQueryString(merged),
        );
        return response.data;
    },
    backfillConflicts: async (
        accountId: IdParam,
        params: QueryFilters = {},
        includeProcessed = false,
    ) => {
        const merged: QueryFilters = {
            ...params,
            additionalParams: {
                ...(params.additionalParams ?? {}),
                ...(includeProcessed ? {include_processed: '1'} : {}),
            },
        };
        const response = await api.get<GenericPaginatedResponse<ContactBackfillConflictRow>>(
            `accounts/${accountId}/contacts/backfill/conflicts` + queryParamsHelper.buildQueryString(merged),
        );
        return response.data;
    },
};

export interface ContactBackfillSummary {
    unlinked_attendees_count: number;
    unmapped_questions_count: number;
    conflicts_count: number;
}

export interface ContactBackfillUnlinkedAttendee {
    id: number;
    email: string;
    first_name: string | null;
    last_name: string | null;
    event_id: number;
    event_title: string;
    created_at: string | null;
    contact_id: number | null;
    contact_link_ignored_at: string | null;
    status: 'added' | 'ignored' | null;
}

export interface ContactBackfillUnmappedQuestion {
    question_id: number;
    title: string;
    event_id: number;
    event_title: string;
    answer_count: number;
    sample_answers: string[];
    contact_attribute_definition_id: number | null;
    contact_link_ignored_at: string | null;
    status: 'reused' | 'ignored' | null;
}

export interface ContactBackfillConflictRow {
    question_answer_id: number;
    contact_email: string;
    attribute_name: string;
    existing_value: unknown;
    proposed_value: unknown;
    source_order_id: number;
    source_attendee_id: number | null;
    answered_at: string | null;
    event_id: number;
    event_title: string | null;
    processed: boolean;
    decision_applied: 'updated' | 'ignored' | null;
    applied_at: string | null;
}
