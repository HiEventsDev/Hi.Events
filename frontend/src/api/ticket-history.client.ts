import {api} from "./client";
import {GenericDataResponse, IdParam, TicketHistoryEntry} from "../types";

export const ticketHistoryClient = {
    forAttendee: async (eventId: IdParam, attendeeId: IdParam) => {
        const response = await api.get<GenericDataResponse<TicketHistoryEntry[]>>(
            `events/${eventId}/attendees/${attendeeId}/history`,
        );
        return response.data;
    },

    forOrder: async (eventId: IdParam, orderId: IdParam) => {
        const response = await api.get<GenericDataResponse<TicketHistoryEntry[]>>(
            `events/${eventId}/orders/${orderId}/history`,
        );
        return response.data;
    },
};
