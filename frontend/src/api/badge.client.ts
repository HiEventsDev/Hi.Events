import {api} from "./client";
import {
    Badge,
    BadgeDesign,
    BadgeQuery,
    BadgeStats,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
} from "../types";

export const buildBadgeQueryString = (query: BadgeQuery): string => {
    const params = new URLSearchParams();
    if (query.attendee_ids?.length) params.set('attendee_ids', query.attendee_ids.join(','));
    if (query.product_ids?.length) params.set('product_ids', query.product_ids.join(','));
    if (query.order_id) params.set('order_id', String(query.order_id));
    if (query.event_occurrence_id) params.set('event_occurrence_id', String(query.event_occurrence_id));
    if (query.only_unprinted) params.set('only_unprinted', '1');
    if (query.sort_by) params.set('sort_by', query.sort_by);
    if (query.page) params.set('page', String(query.page));
    if (query.per_page) params.set('per_page', String(query.per_page));
    const qs = params.toString();
    return qs ? `?${qs}` : '';
};

export const badgeClient = {
    getDesign: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<BadgeDesign>>(`events/${eventId}/badge-design`);
        return response.data;
    },
    updateDesign: async (eventId: IdParam, design: BadgeDesign) => {
        const response = await api.put<GenericDataResponse<BadgeDesign>>(`events/${eventId}/badge-design`, design);
        return response.data;
    },
    getBadges: async (eventId: IdParam, query: BadgeQuery) => {
        const response = await api.get<GenericPaginatedResponse<Badge>>(`events/${eventId}/badges` + buildBadgeQueryString(query));
        return response.data;
    },
    getStats: async (eventId: IdParam, eventOccurrenceId?: IdParam | null) => {
        const qs = eventOccurrenceId ? `?event_occurrence_id=${eventOccurrenceId}` : '';
        const response = await api.get<GenericDataResponse<BadgeStats>>(`events/${eventId}/badges/stats${qs}`);
        return response.data;
    },
    markPrinted: async (eventId: IdParam, attendeeIds: IdParam[]) => {
        const response = await api.post<GenericDataResponse<{ updated: number }>>(`events/${eventId}/badges/printed`, {
            attendee_ids: attendeeIds,
        });
        return response.data;
    },
};
