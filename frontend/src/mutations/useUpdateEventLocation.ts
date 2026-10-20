import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, UpsertEventLocationPayload} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";
import {GET_EVENT_QUERY_KEY} from "../queries/useGetEvent.ts";

interface Variables {
    eventId: IdParam;
    eventLocation?: UpsertEventLocationPayload | null;
    clearEventLocation?: boolean;
}

export const useUpdateEventLocation = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, eventLocation, clearEventLocation}: Variables) =>
            eventsClient.updateEventLocation(eventId, {
                event_location: eventLocation ?? null,
                clear_event_location: clearEventLocation ?? false,
            }),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_QUERY_KEY, variables.eventId],
            });
        },
    });
};
