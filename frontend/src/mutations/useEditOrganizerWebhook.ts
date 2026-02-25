import { useMutation, useQueryClient } from "@tanstack/react-query";
import { organizerWebhookClient, OrganizerWebhookRequest } from "../api/organizer-webhook.client.ts";
import { IdParam } from "../types.ts";
import { GET_ORGANIZER_WEBHOOKS_QUERY_KEY } from "../queries/useGetOrganizerWebhooks.ts";

export const useEditOrganizerWebhook = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ organizerId, webhookId, webhook }: {
            organizerId: IdParam,
            webhookId: IdParam,
            webhook: OrganizerWebhookRequest
        }) => organizerWebhookClient.update(organizerId, webhookId, webhook),

        onSuccess: () => queryClient.invalidateQueries({ queryKey: [GET_ORGANIZER_WEBHOOKS_QUERY_KEY] })
    });
}
