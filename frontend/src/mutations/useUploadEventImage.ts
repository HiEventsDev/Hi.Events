import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";
import {GET_EVENT_IMAGES_QUERY_KEY} from "../queries/useGetEventImages.ts";
import {GET_EVENT_PUBLIC_QUERY_KEY} from "../queries/useGetEventPublic.ts";

export const useUploadEventImage = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({eventId, image}: {
            image: File,
            eventId: IdParam,
        }) => eventsClient.uploadEventImage(eventId, image),
        {
            onSuccess: (_, variables) => {
                queryClient.invalidateQueries([GET_EVENT_IMAGES_QUERY_KEY, variables.eventId]);
                queryClient.invalidateQueries([GET_EVENT_PUBLIC_QUERY_KEY, variables.eventId]);
            }
        }
    )
}
