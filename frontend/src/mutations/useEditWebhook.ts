import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {webhookClient, WebhookRequest} from "../api/webhook.client.ts";
import {GET_WEBHOOK_QUERY_KEY} from "../queries/useGetWebhook.ts";
import {GET_WEBHOOKS_QUERY_KEY} from "../queries/useGetWebhooks.ts";

export const useEditWebhook = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({webhookData, eventId, webhookId}: {
            eventId: IdParam,
            webhookData: WebhookRequest,
            webhookId: IdParam,
        }) => webhookClient.update(
            eventId,
            webhookId,
            webhookData,
        ),

        onSuccess: (_, variables) => {
            return Promise.all([
                queryClient.invalidateQueries({
                    queryKey: [
                        GET_WEBHOOK_QUERY_KEY,
                        variables.eventId,
                        variables.webhookId,
                    ]
                }),
                queryClient.invalidateQueries({queryKey: [GET_WEBHOOKS_QUERY_KEY]}),
            ]);
        }
    });
}
