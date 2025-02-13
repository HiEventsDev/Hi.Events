import {webhookClient} from "../api/webhook.client.ts";
import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";

export const GET_WEBHOOK_QUERY_KEY = 'getEventWebhooks';

export const useGetWebhook = (eventId: IdParam, webhookId: IdParam) => {
    return useQuery({
        queryKey: [GET_WEBHOOK_QUERY_KEY, eventId, webhookId],
        queryFn: async () => await webhookClient.get(eventId, webhookId),
    });
}
