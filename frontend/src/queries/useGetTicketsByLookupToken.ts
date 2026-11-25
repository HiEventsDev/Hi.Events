import {useQuery} from "@tanstack/react-query";
import {ticketLookupClient} from "../api/ticket-lookup.client.ts";
import {LookupTicket} from "../types.ts";

export const GET_TICKETS_BY_LOOKUP_TOKEN_QUERY_KEY = "getTicketsByLookupToken";

export const useGetTicketsByLookupToken = (token: string | undefined) => useQuery<LookupTicket[], Error>({
    queryKey: [GET_TICKETS_BY_LOOKUP_TOKEN_QUERY_KEY, token],
    queryFn: async () => (await ticketLookupClient.getTicketsByToken(token as string)).data,
    enabled: !!token,
    refetchOnWindowFocus: false,
    retry: false,
});
