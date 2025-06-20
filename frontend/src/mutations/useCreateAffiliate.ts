import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {affiliateClient, CreateAffiliateRequest} from "../api/affiliate.client.ts";
import {GET_AFFILIATES_QUERY_KEY} from "../queries/useGetAffiliates.ts";
import {useQueryClient} from "@tanstack/react-query";
import {GET_EVENT_QUERY_KEY} from "../queries/useGetEvent.ts";

export const useCreateAffiliate = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, affiliateData}: {
            eventId: IdParam,
            affiliateData: CreateAffiliateRequest
        }) => affiliateClient.create(eventId, affiliateData),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({queryKey: [GET_EVENT_QUERY_KEY, variables.eventId]})
            return queryClient.invalidateQueries({queryKey: [GET_AFFILIATES_QUERY_KEY]});
        }
    });
}