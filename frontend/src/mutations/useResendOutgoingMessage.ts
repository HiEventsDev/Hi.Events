import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {outgoingMessagesClient} from "../api/outgoing-messages.client.ts";
import {GET_OUTGOING_MESSAGES_QUERY_KEY} from "../queries/useGetOutgoingMessages.ts";
import {GET_DELIVERY_ISSUES_QUERY_KEY} from "../queries/useGetDeliveryIssues.ts";

export const useResendOutgoingMessage = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, messageId, email}: {
            eventId: IdParam,
            messageId: IdParam,
            email?: string,
        }) =>
            outgoingMessagesClient.resend(eventId, messageId, email),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_OUTGOING_MESSAGES_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_DELIVERY_ISSUES_QUERY_KEY]});
        },
    });
};
