import { organizerWebhookClient } from "../api/organizer-webhook.client.ts";
import { useQuery } from "@tanstack/react-query";
import { IdParam } from "../types.ts";

export const GET_ORGANIZER_WEBHOOKS_QUERY_KEY = 'getOrganizerWebhooks';

export const useGetOrganizerWebhooks = (organizerId: IdParam) => {
    return useQuery({
        queryKey: [GET_ORGANIZER_WEBHOOKS_QUERY_KEY, organizerId],
        queryFn: async () => await organizerWebhookClient.all(organizerId),
    });
}
