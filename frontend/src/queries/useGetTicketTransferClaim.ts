import {useQuery} from "@tanstack/react-query";
import {AxiosError} from "axios";
import {ticketTransferClient} from "../api/ticket-transfer.client.ts";
import {TicketTransferClaim} from "../types.ts";

export const GET_TICKET_TRANSFER_CLAIM_QUERY_KEY = 'getTicketTransferClaim';

export const useGetTicketTransferClaim = (token: string | undefined) => useQuery<TicketTransferClaim, AxiosError>({
    queryKey: [GET_TICKET_TRANSFER_CLAIM_QUERY_KEY, token],
    queryFn: () => ticketTransferClient.getClaim(token as string),
    enabled: !!token,
    refetchOnWindowFocus: false,
    retry: false,
});
