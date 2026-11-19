import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";
import {retryUnlessRejected} from "./retryUnlessRejected.ts";

export const useGetCrmCampaign = (organizerId: IdParam | undefined, campaignId: IdParam | undefined) => {
    return useQuery({
        queryKey: crmKeys.campaign(organizerId, campaignId),
        queryFn: async () => {
            const {data} = await crmClient.campaign(organizerId, campaignId);
            return data;
        },
        enabled: !!organizerId && !!campaignId,
        retry: retryUnlessRejected,
        refetchInterval: (query) => {
            const status = query.state.data?.status;
            if (status === 'SENDING') {
                return 5000;
            }
            return status === 'PENDING_REVIEW' || status === 'SCHEDULED' ? 30_000 : false;
        },
    });
};
