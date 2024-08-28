import {useMutation, useQueryClient} from "@tanstack/react-query";
import {Event, IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";
import {GET_EVENT_QUERY_KEY} from "../queries/useGetEvent.ts";

export const useUpdateEvent = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventData, eventId}: {
            eventData: Partial<Event>,
            eventId: IdParam,
        }) => eventsClient.update(eventId, eventData),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_QUERY_KEY, variables.eventId]
            });
        }
    });
}
