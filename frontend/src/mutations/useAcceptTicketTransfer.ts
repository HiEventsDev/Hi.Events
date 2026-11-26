import {useMutation} from "@tanstack/react-query";
import {AcceptTicketTransferData, ticketTransferClient} from "../api/ticket-transfer.client.ts";

export const useAcceptTicketTransfer = () => useMutation({
    mutationFn: ({token, data}: { token: string; data: AcceptTicketTransferData }) =>
        ticketTransferClient.accept(token, data),
});
