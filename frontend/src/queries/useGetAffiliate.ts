import {useQuery} from "@tanstack/react-query";
import {affiliateClient} from "../api/affiliate.client.ts";
import {IdParam} from "../types.ts";

export const GET_AFFILIATE_QUERY_KEY = 'getAffiliate';

export const useGetAffiliate = (eventId: IdParam, affiliateId: IdParam) => {
    return useQuery({
        queryKey: [GET_AFFILIATE_QUERY_KEY, affiliateId],
        queryFn: () => affiliateClient.findById(eventId, affiliateId),
        enabled: !!affiliateId,
    });
};