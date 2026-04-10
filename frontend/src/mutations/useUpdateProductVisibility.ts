import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {GET_PRODUCT_VISIBILITY_QUERY_KEY} from "../queries/useGetProductVisibility.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const useUpdateProductVisibility = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, occurrenceId, productIds}: {
            eventId: IdParam,
            occurrenceId: IdParam,
            productIds: IdParam[],
        }) => eventOccurrenceClient.updateProductVisibility(eventId, occurrenceId, productIds),

        onSuccess: (_, variables) => queryClient
            .invalidateQueries({queryKey: [GET_PRODUCT_VISIBILITY_QUERY_KEY, variables.eventId, variables.occurrenceId]})
    });
};
