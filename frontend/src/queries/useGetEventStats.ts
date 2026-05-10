import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";

export const GET_EVENT_STATS_QUERY_KEY = 'getEventStats';

interface UseGetEventStatsOptions {
    occurrenceId?: IdParam;
    dateRange?: string;
    enabled?: boolean;
}

export const useGetEventStats = (eventId: IdParam, options: UseGetEventStatsOptions = {}) => {
    const {occurrenceId, dateRange, enabled = true} = options;
    return useQuery({
        queryKey: [GET_EVENT_STATS_QUERY_KEY, eventId, occurrenceId, dateRange],
        queryFn: async () => {
            const {data} = await eventsClient.getEventStats(eventId, {occurrenceId, dateRange});
            return data;
        },
        enabled,
    });
};
