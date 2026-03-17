import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";
import {GET_EVENTS_QUERY_KEY} from "../queries/useGetEvents.ts";

export const useDeleteEvent = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId}: { eventId: IdParam }) => eventsClient.delete(eventId),

        onSuccess: () => {
            return Promise.all([
                queryClient.invalidateQueries({queryKey: [GET_EVENTS_QUERY_KEY]}),
            ]);
        }
    });
};
