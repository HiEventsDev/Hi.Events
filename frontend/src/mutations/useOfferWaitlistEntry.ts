import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {waitlistClient} from "../api/waitlist.client.ts";
import {GET_EVENT_WAITLIST_ENTRIES_QUERY_KEY} from "../queries/useGetEventWaitlistEntries.ts";
import {GET_WAITLIST_STATS_QUERY_KEY} from "../queries/useGetWaitlistStats.ts";

export const useOfferWaitlistEntry = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, productPriceId, quantity}: {
            eventId: IdParam,
            productPriceId: number,
            quantity?: number,
        }) => waitlistClient.offerNext(eventId, productPriceId, quantity),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({queryKey: [GET_EVENT_WAITLIST_ENTRIES_QUERY_KEY, variables.eventId]});
            queryClient.invalidateQueries({queryKey: [GET_WAITLIST_STATS_QUERY_KEY, variables.eventId]});
        },
    });
};
