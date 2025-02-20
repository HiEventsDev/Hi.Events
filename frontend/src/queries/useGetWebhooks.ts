import {webhookClient} from "../api/webhook.client.ts";
import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";

export const GET_WEBHOOKS_QUERY_KEY = 'getEventWebhooks';

export const useGetWebhooks = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_WEBHOOKS_QUERY_KEY, eventId],
        queryFn: async () => await webhookClient.all(eventId),
    });
}
