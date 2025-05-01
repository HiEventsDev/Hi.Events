import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, ImageType} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";
import {GET_EVENT_IMAGES_QUERY_KEY} from "../queries/useGetEventImages.ts";
import {GET_EVENT_PUBLIC_QUERY_KEY} from "../queries/useGetEventPublic.ts";

export const useUploadEventImage = (type: ImageType = 'EVENT_COVER') => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, image}: {
            image: File,
            eventId: IdParam,
        }) => eventsClient.uploadEventImage(eventId, image, type as ImageType),

        onSuccess: (_, variables) =>
            Promise.all([
                queryClient.invalidateQueries({
                    queryKey: [GET_EVENT_IMAGES_QUERY_KEY, variables.eventId]
                }),
                queryClient.invalidateQueries({
                    queryKey: [GET_EVENT_PUBLIC_QUERY_KEY, variables.eventId]
                })
            ])
    });
}
