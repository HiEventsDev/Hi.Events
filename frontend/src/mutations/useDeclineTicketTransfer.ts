import {useMutation, useQueryClient} from "@tanstack/react-query";
import {ticketTransferClient} from "../api/ticket-transfer.client.ts";
import {GET_TICKET_TRANSFER_CLAIM_QUERY_KEY} from "../queries/useGetTicketTransferClaim.ts";

export const useDeclineTicketTransfer = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({token}: { token: string }) => ticketTransferClient.decline(token),
        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_TICKET_TRANSFER_CLAIM_QUERY_KEY]}),
    });
};
