import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {affiliateClient} from "../api/affiliate.client.ts";
import {GET_AFFILIATES_QUERY_KEY} from "../queries/useGetAffiliates.ts";
import {useQueryClient} from "@tanstack/react-query";

export const useDeleteAffiliate = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, affiliateId}: {
            eventId: IdParam,
            affiliateId: IdParam
        }) => affiliateClient.delete(eventId, affiliateId),

        onSuccess: () => {
            return queryClient.invalidateQueries({queryKey: [GET_AFFILIATES_QUERY_KEY]});
        }
    });
}