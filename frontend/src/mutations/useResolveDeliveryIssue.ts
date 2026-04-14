import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {deliveryIssuesClient} from "../api/delivery-issues.client.ts";
import {GET_DELIVERY_ISSUES_QUERY_KEY} from "../queries/useGetDeliveryIssues.ts";

export const useResolveDeliveryIssue = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, messageId, sourceType}: {
            eventId: IdParam,
            messageId: IdParam,
            sourceType: 'transaction' | 'announcement',
        }) =>
            deliveryIssuesClient.resolve(eventId, messageId, sourceType),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_DELIVERY_ISSUES_QUERY_KEY]});
        },
    });
};
