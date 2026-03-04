import { organizerWebhookClient } from "../api/organizer-webhook.client.ts";
import { useQuery } from "@tanstack/react-query";
import { IdParam } from "../types.ts";

export const GET_ORGANIZER_WEBHOOK_LOGS_QUERY_KEY = 'getOrganizerWebhookLogs';

export const useGetOrganizerWebhookLogs = (organizerId: IdParam, webhookId: IdParam) => {
    return useQuery({
        queryKey: [GET_ORGANIZER_WEBHOOK_LOGS_QUERY_KEY, organizerId, webhookId],
        queryFn: async () => await organizerWebhookClient.logs(organizerId, webhookId),
        enabled: !!webhookId
    });
}
