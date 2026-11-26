import {publicApi} from "./public-client.ts";
import {GenericDataResponse, PendingTicketTransfer, TicketTransferClaim} from "../types.ts";
import {selfServiceHeaders, SelfServiceLink} from "../utilites/selfServiceSession.ts";

export interface TicketLink {
    eventId: string | number;
    attendeeShortId: string;
    orderShortId?: string;
}

export interface AcceptTicketTransferData {
    first_name: string;
    last_name: string;
    questions: { question_id: number; response: unknown }[];
}

export const ticketSessionLink = (link: TicketLink): SelfServiceLink => link.orderShortId
    ? {eventId: link.eventId, orderShortId: link.orderShortId}
    : {eventId: link.eventId, attendeeShortId: link.attendeeShortId};

const transferPath = (link: TicketLink): string => link.orderShortId
    ? `/events/${link.eventId}/order/${link.orderShortId}/attendees/${link.attendeeShortId}/transfer`
    : `/events/${link.eventId}/attendees/${link.attendeeShortId}/transfer`;

export const ticketTransferClient = {
    start: async (link: TicketLink, recipientEmail: string): Promise<PendingTicketTransfer> => {
        const response = await publicApi.post<GenericDataResponse<PendingTicketTransfer>>(
            transferPath(link),
            {recipient_email: recipientEmail},
            {headers: selfServiceHeaders(ticketSessionLink(link))},
        );
        return response.data.data;
    },

    cancel: async (link: TicketLink): Promise<void> => {
        await publicApi.delete(transferPath(link), {headers: selfServiceHeaders(ticketSessionLink(link))});
    },

    getClaim: async (token: string): Promise<TicketTransferClaim> => {
        const response = await publicApi.get<GenericDataResponse<TicketTransferClaim>>(`/ticket-transfers/${encodeURIComponent(token)}`);
        return response.data.data;
    },

    accept: async (token: string, data: AcceptTicketTransferData): Promise<{ event_id: number; attendee_short_id: string }> => {
        const response = await publicApi.post(`/ticket-transfers/${encodeURIComponent(token)}/accept`, data);
        return response.data;
    },

    decline: async (token: string): Promise<void> => {
        await publicApi.post(`/ticket-transfers/${encodeURIComponent(token)}/decline`);
    },
};
