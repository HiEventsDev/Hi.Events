import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, UpsertPriceOverrideRequest} from "../types.ts";
import {GET_PRICE_OVERRIDES_QUERY_KEY} from "../queries/useGetPriceOverrides.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const useUpsertPriceOverride = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, occurrenceId, data}: {
            eventId: IdParam,
            occurrenceId: IdParam,
            data: UpsertPriceOverrideRequest,
        }) => eventOccurrenceClient.upsertPriceOverride(eventId, occurrenceId, data),

        onSuccess: (_, variables) => queryClient
            .invalidateQueries({queryKey: [GET_PRICE_OVERRIDES_QUERY_KEY, variables.eventId, variables.occurrenceId]})
    });
};
