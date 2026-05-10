import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {GET_PRICE_OVERRIDES_QUERY_KEY} from "../queries/useGetPriceOverrides.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const useDeletePriceOverride = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, occurrenceId, overrideId}: {
            eventId: IdParam,
            occurrenceId: IdParam,
            overrideId: IdParam,
        }) => eventOccurrenceClient.deletePriceOverride(eventId, occurrenceId, overrideId),

        onSuccess: (_, variables) => queryClient
            .invalidateQueries({queryKey: [GET_PRICE_OVERRIDES_QUERY_KEY, variables.eventId, variables.occurrenceId]})
    });
};
