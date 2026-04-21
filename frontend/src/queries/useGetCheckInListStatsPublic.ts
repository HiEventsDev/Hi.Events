import {useQuery} from "@tanstack/react-query";
import {publicCheckInClient} from "../api/check-in.client";
import {IdParam} from "../types.ts";

export const GET_CHECK_IN_LIST_STATS_PUBLIC_QUERY_KEY = "getCheckInListStatsPublic";

export const useGetCheckInListStatsPublic = (checkInListShortId: IdParam, enabled: boolean = true) => {
    return useQuery({
        queryKey: [GET_CHECK_IN_LIST_STATS_PUBLIC_QUERY_KEY, checkInListShortId],
        queryFn: () => publicCheckInClient.getCheckInListStats(checkInListShortId),
        enabled,
        retry: false,
    });
};
