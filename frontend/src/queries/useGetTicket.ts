import {useQuery} from "@tanstack/react-query";
import {IdParam, Ticket} from "../types.ts";
import {ticketClient} from "../api/ticket.client.ts";

export const GET_TICKETS_QUERY_KEY = 'getTickets';

export const useGetTicket = (eventId: IdParam, ticketId: IdParam) => {
    return useQuery<Ticket>({
        queryKey: [GET_TICKETS_QUERY_KEY, eventId, ticketId],

        queryFn: async () => {
            const {data} = await ticketClient.findById(eventId, ticketId);
            return data;
        }
    });
};
