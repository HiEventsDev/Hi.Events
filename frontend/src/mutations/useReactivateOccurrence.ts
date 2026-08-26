import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {GET_EVENT_OCCURRENCES_QUERY_KEY} from "../queries/useGetEventOccurrences.ts";
import {GET_EVENT_OCCURRENCE_QUERY_KEY} from "../queries/useGetEventOccurrence.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";
import {GET_EVENT_QUERY_KEY} from "../queries/useGetEvent.ts";

export const useReactivateOccurrence = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, occurrenceId}: {
            eventId: IdParam,
            occurrenceId: IdParam,
        }) => eventOccurrenceClient.reactivate(eventId, occurrenceId),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_OCCURRENCE_QUERY_KEY, variables.eventId, variables.occurrenceId]
            });
            return Promise.all([
                queryClient.invalidateQueries({queryKey: [GET_EVENT_OCCURRENCES_QUERY_KEY]}),
                queryClient.invalidateQueries({queryKey: [GET_EVENT_QUERY_KEY]}),
            ]);
        }
    });
};
