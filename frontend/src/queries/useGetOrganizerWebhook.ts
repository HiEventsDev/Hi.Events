import { organizerWebhookClient } from "../api/organizer-webhook.client.ts";
import { useQuery } from "@tanstack/react-query";
import { IdParam } from "../types.ts";

export const GET_ORGANIZER_WEBHOOK_QUERY_KEY = 'getOrganizerWebhook';

export const useGetOrganizerWebhook = (organizerId: IdParam, webhookId: IdParam) => {
    return useQuery({
        queryKey: [GET_ORGANIZER_WEBHOOK_QUERY_KEY, organizerId, webhookId],
        queryFn: async () => await organizerWebhookClient.get(organizerId, webhookId),
        enabled: !!webhookId
    });
}
