import {useMutation, useQueryClient} from "@tanstack/react-query";
import {GenerateOccurrencesRequest, IdParam} from "../types.ts";
import {GET_EVENT_OCCURRENCES_QUERY_KEY} from "../queries/useGetEventOccurrences.ts";
import {GET_EVENT_QUERY_KEY} from "../queries/useGetEvent.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const useGenerateOccurrences = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, data}: {
            eventId: IdParam,
            data: GenerateOccurrencesRequest,
        }) => eventOccurrenceClient.generate(eventId, data),

        onSuccess: (_, {eventId}) => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_OCCURRENCES_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_QUERY_KEY, eventId]}),
        ]),
    });
};
