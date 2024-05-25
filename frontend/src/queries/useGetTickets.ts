import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, QueryFilters, Ticket} from "../types.ts";
import {ticketClient} from "../api/ticket.client.ts";

export const GET_TICKETS_QUERY_KEY = 'getTickets';

export const useGetTickets = (eventId: IdParam, pagination: QueryFilters = {pageNumber: 1}) => {
    return useQuery<GenericPaginatedResponse<Ticket>>({
            queryKey: [GET_TICKETS_QUERY_KEY, eventId, pagination],
            queryFn: async () => await ticketClient.all(eventId, pagination),
        }
    )
};