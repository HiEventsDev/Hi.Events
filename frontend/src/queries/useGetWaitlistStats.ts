import {useQuery} from "@tanstack/react-query";
import {waitlistClient} from "../api/waitlist.client.ts";
import {IdParam, WaitlistStats} from "../types.ts";

export const GET_WAITLIST_STATS_QUERY_KEY = 'getWaitlistStats';

export const useGetWaitlistStats = (eventId: IdParam) => {
    return useQuery<WaitlistStats>({
        queryKey: [GET_WAITLIST_STATS_QUERY_KEY, eventId],
        queryFn: async () => {
            return waitlistClient.stats(eventId);
        },
    });
};
