import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types";
import {webhookClient} from "../api/webhook.client";
import {GET_WEBHOOKS_QUERY_KEY} from "../queries/useGetWebhooks";

export const useDeleteWebhook = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, webhookId}: {
            eventId: IdParam,
            webhookId: IdParam
        }) => webhookClient.delete(eventId, webhookId),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_WEBHOOKS_QUERY_KEY]})
    });
}
