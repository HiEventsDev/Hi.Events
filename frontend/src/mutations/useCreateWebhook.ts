import {useMutation, useQueryClient} from "@tanstack/react-query";
import {webhookClient, WebhookRequest} from "../api/webhook.client.ts";
import {IdParam} from "../types.ts";
import {GET_WEBHOOKS_QUERY_KEY} from "../queries/useGetWebhooks.ts";

export const useCreateWebhook = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, webhook}: {
            eventId: IdParam,
            webhook: WebhookRequest
        }) => webhookClient.create(eventId, webhook),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_WEBHOOKS_QUERY_KEY]})
    });
}


