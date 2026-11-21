import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";

export const GET_EVENT_SHARE_SUGGESTIONS_QUERY_KEY = 'getEventShareSuggestions';

export const useGetEventShareSuggestions = (eventId: IdParam | undefined, enabled: boolean) => {
    return useQuery({
        queryKey: [GET_EVENT_SHARE_SUGGESTIONS_QUERY_KEY, eventId],

        queryFn: async () => {
            const {data} = await eventsClient.getShareSuggestions(eventId);
            return data;
        },

        enabled: enabled && !!eventId,
    });
};
