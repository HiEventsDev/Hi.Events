import {useMutation} from "@tanstack/react-query";
import {ticketLookupClient} from "../api/ticket-lookup.client.ts";

export const useSendTicketLookupEmail = () => {
    return useMutation({
        mutationFn: (email: string) => ticketLookupClient.sendTicketLookupEmail(email),
    });
}
