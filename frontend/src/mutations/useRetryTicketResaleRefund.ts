import {useMutation, useQueryClient} from "@tanstack/react-query";
import {ticketResaleListingClient} from "../api/ticket-resale-listing.client.ts";
import {IdParam} from "../types.ts";
import {invalidateTicketResaleQueries} from "./invalidateTicketResaleQueries.ts";

export const useRetryTicketResaleRefund = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, listingId}: {
            eventId: IdParam,
            listingId: IdParam,
        }) => ticketResaleListingClient.retryRefund(eventId, listingId),

        onSuccess: () => invalidateTicketResaleQueries(queryClient),
    });
};
