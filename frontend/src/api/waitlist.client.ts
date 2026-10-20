import {api} from "./client";
import {
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    JoinWaitlistRequest,
    QueryFilters,
    WaitlistEntry,
    WaitlistStats,
} from "../types";
import {publicApi} from "./public-client.ts";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const waitlistClient = {
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<WaitlistEntry>>(
            `events/${eventId}/waitlist` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },

    stats: async (eventId: IdParam, eventOccurrenceId?: IdParam | null) => {
        const query = new URLSearchParams();

        if (eventOccurrenceId) {
            query.set('event_occurrence_id', String(eventOccurrenceId));
        }

        const queryString = query.toString();
        const response = await api.get<WaitlistStats>(
            `events/${eventId}/waitlist/stats${queryString ? `?${queryString}` : ''}`,
        );
        return response.data;
    },

    offerNext: async (eventId: IdParam, productPriceId: number, quantity: number = 1, eventOccurrenceId?: IdParam | null) => {
        const response = await api.post<GenericDataResponse<WaitlistEntry[]>>(
            `events/${eventId}/waitlist/offer-next`,
            {product_price_id: productPriceId, quantity, event_occurrence_id: eventOccurrenceId},
        );
        return response.data;
    },

    offerEntry: async (eventId: IdParam, entryId: IdParam) => {
        const response = await api.post<GenericDataResponse<WaitlistEntry[]>>(
            `events/${eventId}/waitlist/offer-next`,
            {entry_id: entryId},
        );
        return response.data;
    },

    remove: async (eventId: IdParam, entryId: IdParam) => {
        return api.delete(`events/${eventId}/waitlist/${entryId}`);
    },
};

export const waitlistClientPublic = {
    join: async (eventId: IdParam, data: JoinWaitlistRequest) => {
        const response = await publicApi.post<GenericDataResponse<WaitlistEntry>>(
            `events/${eventId}/waitlist`,
            data,
        );
        return response.data;
    },

    cancel: async (eventId: IdParam, cancelToken: string) => {
        return publicApi.delete(`events/${eventId}/waitlist/${cancelToken}`);
    },
};
