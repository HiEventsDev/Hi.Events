import {useQuery} from "@tanstack/react-query";
import {eventsClient} from "../api/event.client.ts";
import {IdParam} from "../types.ts";

export const GET_EVENT_CHECK_IN_STATS_QUERY_KEY = 'getEventCheckInStats';

export const useGetEventCheckInStats = (eventId: IdParam) => {
    return useQuery(
        [GET_EVENT_CHECK_IN_STATS_QUERY_KEY, eventId],
        async () => {
            const {data} = await eventsClient.getEventCheckInStats(eventId);
            return data;
        }
    )
};