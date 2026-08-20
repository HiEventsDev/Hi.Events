import {useMutation, useQueryClient} from "@tanstack/react-query";
import {BulkUpdateOccurrencesRequest, IdParam} from "../types.ts";
import {GET_EVENT_OCCURRENCES_QUERY_KEY} from "../queries/useGetEventOccurrences.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";
import {GET_EVENT_QUERY_KEY} from "../queries/useGetEvent.ts";

export const useBulkUpdateOccurrences = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, data}: {
            eventId: IdParam,
            data: BulkUpdateOccurrencesRequest,
        }) => eventOccurrenceClient.bulkUpdate(eventId, data),

        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_OCCURRENCES_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_QUERY_KEY]}),
        ])
    });
};
