import {api} from "./client";
import {GenericDataResponse, GenericPaginatedResponse, IdParam, QueryFilters, TicketResaleListing} from "../types.ts";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const ticketResaleListingClient = {
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<TicketResaleListing>>(
            `events/${eventId}/resale-listings` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },

    close: async (eventId: IdParam, listingId: IdParam) => {
        const response = await api.post<GenericDataResponse<TicketResaleListing>>(
            `events/${eventId}/resale-listings/${listingId}/close`,
        );
        return response.data;
    },

    retryRefund: async (eventId: IdParam, listingId: IdParam) => {
        const response = await api.post<GenericDataResponse<TicketResaleListing>>(
            `events/${eventId}/resale-listings/${listingId}/retry-refund`,
        );
        return response.data;
    },
};
