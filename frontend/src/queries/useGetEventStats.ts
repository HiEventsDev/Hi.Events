import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";

export const GET_EVENT_STATS_QUERY_KEY = 'getEventStats';

interface UseGetEventStatsOptions {
    occurrenceId?: IdParam;
    dateRange?: string;
    startDate?: string;
    endDate?: string;
    enabled?: boolean;
}

export const useGetEventStats = (eventId: IdParam, options: UseGetEventStatsOptions = {}) => {
    const {occurrenceId, dateRange, startDate, endDate, enabled = true} = options;
    return useQuery({
        queryKey: [GET_EVENT_STATS_QUERY_KEY, eventId, occurrenceId, dateRange, startDate, endDate],
        queryFn: async () => {
            const {data} = await eventsClient.getEventStats(eventId, {occurrenceId, dateRange, startDate, endDate});
            return data;
        },
        enabled,
    });
};
