import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";

export const GET_EVENT_STATS_QUERY_KEY = 'getEventStats';

export const useGetEventStats = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_EVENT_STATS_QUERY_KEY, eventId],

        queryFn: async () => {
            const {data} = await eventsClient.getEventStats(eventId);
            return data;
        }
    });
};