import { useMutation, useQueryClient } from "@tanstack/react-query";
import { IdParam } from "../types";
import { organizerWebhookClient } from "../api/organizer-webhook.client";
import { GET_ORGANIZER_WEBHOOKS_QUERY_KEY } from "../queries/useGetOrganizerWebhooks";

export const useDeleteOrganizerWebhook = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ organizerId, webhookId }: {
            organizerId: IdParam,
            webhookId: IdParam
        }) => organizerWebhookClient.delete(organizerId, webhookId),

        onSuccess: () => queryClient.invalidateQueries({ queryKey: [GET_ORGANIZER_WEBHOOKS_QUERY_KEY] })
    });
}
