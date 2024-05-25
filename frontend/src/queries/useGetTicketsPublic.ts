import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, QueryFilters, Ticket} from "../types.ts";
import {ticketClient} from "../api/ticket.client.ts";

export const GET_PUBLIC_TICKETS_QUERY_KEY = 'getTicketsPublic';

export const useGetTickets = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<Ticket>>({
            queryKey: [GET_PUBLIC_TICKETS_QUERY_KEY, eventId, pagination],
            queryFn: async () => await ticketClient.all(eventId, pagination),
        }
    )
};