import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";
import {GET_EVENT_SHARE_SUGGESTIONS_QUERY_KEY} from "../queries/useGetEventShareSuggestions.ts";

export const useGenerateEventShareSuggestions = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId}: { eventId: IdParam }) => eventsClient.generateShareSuggestions(eventId),

        onSuccess: (response, variables) => {
            queryClient.setQueryData([GET_EVENT_SHARE_SUGGESTIONS_QUERY_KEY, variables.eventId], response.data);
        },
    });
};
