import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {affiliateClient, UpdateAffiliateRequest} from "../api/affiliate.client.ts";
import {GET_AFFILIATES_QUERY_KEY} from "../queries/useGetAffiliates.ts";
import {useQueryClient} from "@tanstack/react-query";
import {GET_AFFILIATE_QUERY_KEY} from "../queries/useGetAffiliate.ts";

export const useUpdateAffiliate = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, affiliateId, affiliateData}: {
            eventId: IdParam,
            affiliateId: IdParam,
            affiliateData: UpdateAffiliateRequest
        }) => affiliateClient.update(eventId, affiliateId, affiliateData),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({queryKey: [GET_AFFILIATE_QUERY_KEY, variables.affiliateId]});
            return queryClient.invalidateQueries({queryKey: [GET_AFFILIATES_QUERY_KEY]});
        }
    });
}