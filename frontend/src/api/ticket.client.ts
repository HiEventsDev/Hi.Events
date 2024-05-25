import {api} from "./client";
import {
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    QueryFilters, SortableItem,
    Ticket,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";
import {publicApi} from "./public-client.ts";

export const ticketClient = {
    findById: async (eventId: IdParam, ticketId: IdParam) => {
        const response = await api.get<GenericDataResponse<Ticket>>(`/events/${eventId}/tickets/${ticketId}`);
        return response.data;
    },
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<Ticket>>(
            `/events/${eventId}/tickets` + queryParamsHelper.buildQueryString(pagination)
        );
        return response.data;
    },
    create: async (eventId: IdParam, ticket: Ticket) => {
        const response = await api.post<GenericDataResponse<Ticket>>(`events/${eventId}/tickets`, ticket);
        return response.data;
    },
    update: async (eventId: IdParam, ticketId: IdParam, ticket: Ticket) => {
        const response = await api.put<GenericDataResponse<Ticket>>(`events/${eventId}/tickets/${ticketId}`, ticket);
        return response.data;
    },
    delete: async (eventId: IdParam, ticketId: IdParam) => {
        const response = await api.delete<GenericDataResponse<Ticket>>(`/events/${eventId}/tickets/${ticketId}`);
        return response.data;
    },
    sortTickets: async (eventId: IdParam, ticketSort: SortableItem[]) => {
        return await api.post(`/events/${eventId}/tickets/sort`, ticketSort);
    }
}

export const ticketClientPublic = {
    findByEventId: async (eventId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<Ticket>>(`/events/${eventId}/tickets`);
        return response.data;
    },
}

