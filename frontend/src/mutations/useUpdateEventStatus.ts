import {eventsClient} from "../api/event.client.ts";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {GET_EVENT_QUERY_KEY} from "../queries/useGetEvent.ts";
import {GET_EVENTS_QUERY_KEY} from "../queries/useGetEvents.ts";

export const useUpdateEventStatus = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({eventId, status}: {
            eventId: IdParam,
            status: string,
        }) => eventsClient.updateEventStatus(eventId, status),
        {
            onSuccess: (_, variables) => {
                queryClient.invalidateQueries([GET_EVENT_QUERY_KEY, variables.eventId]);
                queryClient.invalidateQueries([GET_EVENTS_QUERY_KEY]);
            }
        }
    )
}
