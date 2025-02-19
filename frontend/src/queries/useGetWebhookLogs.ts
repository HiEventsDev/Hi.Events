import {webhookClient} from "../api/webhook.client.ts";
import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";

export const GET_WEBHOOK_LOGS_QUERY_KEY = 'getEventWebhookLogs';

export const useGetWebhookLogs = (eventId: IdParam, webhookId: IdParam) => {
    return useQuery({
        queryKey: [GET_WEBHOOK_LOGS_QUERY_KEY, eventId, webhookId],
        queryFn: async () => await webhookClient.logs(eventId, webhookId),
    });
}
