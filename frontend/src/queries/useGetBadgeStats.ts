import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {badgeClient} from "../api/badge.client.ts";

export const GET_BADGE_STATS_QUERY_KEY = 'getBadgeStats';

export const useGetBadgeStats = (eventId: IdParam, eventOccurrenceId?: IdParam | null) => {
    return useQuery({
        queryKey: [GET_BADGE_STATS_QUERY_KEY, eventId, eventOccurrenceId ?? null],
        queryFn: async () => {
            const {data} = await badgeClient.getStats(eventId, eventOccurrenceId);
            return data;
        },
        enabled: !!eventId,
    });
};
