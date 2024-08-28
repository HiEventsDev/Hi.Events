import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";
import {GET_EVENT_IMAGES_QUERY_KEY} from "../queries/useGetEventImages.ts";
import {GET_EVENT_PUBLIC_QUERY_KEY} from "../queries/useGetEventPublic.ts";
import {GET_EVENT_SETTINGS_QUERY_KEY} from "../queries/useGetEventSettings.ts";

export const useDeleteEventImage = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, imageId}: {
            eventId: IdParam,
            imageId: IdParam,
        }) => eventsClient.deleteEventImage(eventId, imageId),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_IMAGES_QUERY_KEY, variables.eventId]
            });
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_PUBLIC_QUERY_KEY, variables.eventId]
            });
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_SETTINGS_QUERY_KEY, variables.eventId]
            });
        }
    });
}
