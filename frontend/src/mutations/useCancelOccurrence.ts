import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {GET_EVENT_OCCURRENCES_QUERY_KEY} from "../queries/useGetEventOccurrences.ts";
import {GET_EVENT_OCCURRENCE_QUERY_KEY} from "../queries/useGetEventOccurrence.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const useCancelOccurrence = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, occurrenceId, refundOrders = false}: {
            eventId: IdParam,
            occurrenceId: IdParam,
            refundOrders?: boolean,
        }) => eventOccurrenceClient.cancel(eventId, occurrenceId, refundOrders),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_OCCURRENCE_QUERY_KEY, variables.eventId, variables.occurrenceId]
            });
            return queryClient.invalidateQueries({queryKey: [GET_EVENT_OCCURRENCES_QUERY_KEY]});
        }
    });
};
