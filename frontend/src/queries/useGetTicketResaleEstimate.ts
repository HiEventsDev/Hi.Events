import {useQuery} from "@tanstack/react-query";
import {AxiosError} from "axios";
import {TicketResaleLink, ticketResaleClient} from "../api/ticket-resale.client.ts";
import {TicketResaleEstimate} from "../types.ts";

export const GET_TICKET_RESALE_ESTIMATE_QUERY_KEY = 'getTicketResaleEstimate';

export const useGetTicketResaleEstimate = (link: TicketResaleLink | null) => {
    return useQuery<TicketResaleEstimate, AxiosError>({
        queryKey: [GET_TICKET_RESALE_ESTIMATE_QUERY_KEY, link?.eventId, link?.orderShortId, link?.attendeeShortId],
        queryFn: () => ticketResaleClient.estimate(link as TicketResaleLink),
        enabled: !!link,
        refetchOnWindowFocus: false,
        staleTime: 0,
        gcTime: 0,
        retry: false,
    });
};
