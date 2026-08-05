import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, UpsertEventOccurrenceRequest} from "../types.ts";
import {GET_EVENT_OCCURRENCES_QUERY_KEY} from "../queries/useGetEventOccurrences.ts";
import {GET_EVENT_OCCURRENCE_QUERY_KEY} from "../queries/useGetEventOccurrence.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const useUpdateEventOccurrence = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, occurrenceId, data}: {
            eventId: IdParam,
            occurrenceId: IdParam,
            data: UpsertEventOccurrenceRequest,
        }) => eventOccurrenceClient.update(eventId, occurrenceId, data),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_OCCURRENCE_QUERY_KEY, variables.eventId, variables.occurrenceId]
            });
            return queryClient.invalidateQueries({queryKey: [GET_EVENT_OCCURRENCES_QUERY_KEY]});
        }
    });
};
