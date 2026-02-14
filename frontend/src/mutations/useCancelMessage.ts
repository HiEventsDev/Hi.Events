import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {messagesClient} from "../api/messages.client.ts";
import {GET_EVENT_MESSAGES_QUERY_KEY} from "../queries/useGetEventMessages.ts";

export const useCancelMessage = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, messageId}: {
            eventId: IdParam,
            messageId: IdParam,
        }) => messagesClient.cancel(eventId, messageId),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_MESSAGES_QUERY_KEY, variables.eventId]
            });
        }
    });
}
