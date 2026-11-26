import {api} from './client.ts';
import {GenericDataResponse, LookupTicket, Order} from "../types.ts";

export const ticketLookupClient = {
    sendTicketLookupEmail: async (email: string) => {
        const response = await api.post<GenericDataResponse<{ message: string }>>('public/ticket-lookup', { email });
        return response.data;
    },

    getOrdersByToken: async (token: string) => {
        const response = await api.get<GenericDataResponse<Order[]>>(`public/ticket-lookup/${encodeURIComponent(token)}`);
        return response.data;
    },

    getTicketsByToken: async (token: string) => {
        const response = await api.get<GenericDataResponse<LookupTicket[]>>(`public/ticket-lookup/${encodeURIComponent(token)}/tickets`);
        return response.data;
    },
}
