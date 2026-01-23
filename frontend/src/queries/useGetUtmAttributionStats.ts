import {useQuery} from "@tanstack/react-query";
import {adminClient, GetUtmAttributionStatsParams} from "../api/admin.client";

export const useGetUtmAttributionStats = (params: GetUtmAttributionStatsParams = {}) => {
    return useQuery({
        queryKey: ['admin', 'attribution', 'stats', params],
        queryFn: () => adminClient.getUtmAttributionStats(params),
    });
};
