import {useQuery} from "@tanstack/react-query";
import {affiliateClient} from "../api/affiliate.client.ts";
import {IdParam, QueryFilters} from "../types.ts";

export const GET_AFFILIATES_QUERY_KEY = 'getAffiliates';

export const useGetAffiliates = (eventId: IdParam, queryFilters: QueryFilters = {}) => {
    return useQuery({
        queryKey: [GET_AFFILIATES_QUERY_KEY, eventId, queryFilters],
        queryFn: () => affiliateClient.all(eventId, queryFilters),
    });
};