import {publicApi} from "./public-client.ts";
import {GenericDataResponse, TicketResaleEstimate, TicketResaleState} from "../types.ts";
import {selfServiceHeaders, SelfServiceLink} from "../utilites/selfServiceSession.ts";

export interface TicketResaleLink {
    eventId: string | number;
    orderShortId: string;
    attendeeShortId: string;
}

export const resaleSessionLink = (link: TicketResaleLink): SelfServiceLink => ({
    eventId: link.eventId,
    orderShortId: link.orderShortId,
});

const resalePath = (link: TicketResaleLink): string =>
    `/events/${link.eventId}/order/${link.orderShortId}/attendees/${link.attendeeShortId}/resale`;

const sessionHeaders = (link: TicketResaleLink) => ({headers: selfServiceHeaders(resaleSessionLink(link))});

export const ticketResaleClient = {
    estimate: async (link: TicketResaleLink): Promise<TicketResaleEstimate> => {
        const response = await publicApi.get<GenericDataResponse<TicketResaleEstimate>>(resalePath(link), sessionHeaders(link));
        return response.data.data;
    },

    list: async (link: TicketResaleLink): Promise<TicketResaleState> => {
        const response = await publicApi.post<GenericDataResponse<TicketResaleState>>(resalePath(link), {}, sessionHeaders(link));
        return response.data.data;
    },

    withdraw: async (link: TicketResaleLink): Promise<TicketResaleState> => {
        const response = await publicApi.delete<GenericDataResponse<TicketResaleState>>(resalePath(link), sessionHeaders(link));
        return response.data.data;
    },
};
