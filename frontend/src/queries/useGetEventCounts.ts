import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";

export const GET_EVENT_COUNTS_QUERY_KEY = 'getEventCounts';

export const useGetEventCounts = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_EVENT_COUNTS_QUERY_KEY, eventId],

        queryFn: async () => {
            const {data} = await eventsClient.getEventCounts(eventId);
            return data;
        },

        enabled: !!eventId,
    });
};
