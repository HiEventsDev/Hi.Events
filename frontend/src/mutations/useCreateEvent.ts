import {useMutation, useQueryClient} from "@tanstack/react-query";
import {Event} from "../types.ts";
import {GET_EVENTS_QUERY_KEY} from "../queries/useGetEvents.ts";
import {eventsClient} from "../api/event.client.ts";

export const useCreateEvent = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventData}: {
            eventData: Partial<Event>
        }) => eventsClient.create(eventData),

        onSuccess: () => {
            return queryClient.invalidateQueries({queryKey: [GET_EVENTS_QUERY_KEY]});
        }
    });
}