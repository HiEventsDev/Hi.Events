import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {ticketResaleListingClient} from "../api/ticket-resale-listing.client.ts";
import {GenericPaginatedResponse, IdParam, QueryFilters, TicketResaleListing} from "../types.ts";

export const GET_TICKET_RESALE_LISTINGS_QUERY_KEY = 'getTicketResaleListings';

export const useGetTicketResaleListings = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<TicketResaleListing>>({
        queryKey: [GET_TICKET_RESALE_LISTINGS_QUERY_KEY, eventId, pagination],
        queryFn: async () => await ticketResaleListingClient.all(eventId, pagination),
        placeholderData: keepPreviousData,
    });
};
