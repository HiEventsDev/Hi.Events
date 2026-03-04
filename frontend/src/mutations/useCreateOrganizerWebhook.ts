import { useMutation, useQueryClient } from "@tanstack/react-query";
import { organizerWebhookClient, OrganizerWebhookRequest } from "../api/organizer-webhook.client.ts";
import { IdParam } from "../types.ts";
import { GET_ORGANIZER_WEBHOOKS_QUERY_KEY } from "../queries/useGetOrganizerWebhooks.ts";

export const useCreateOrganizerWebhook = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ organizerId, webhook }: {
            organizerId: IdParam,
            webhook: OrganizerWebhookRequest
        }) => organizerWebhookClient.create(organizerId, webhook),

        onSuccess: () => queryClient.invalidateQueries({ queryKey: [GET_ORGANIZER_WEBHOOKS_QUERY_KEY] })
    });
}
