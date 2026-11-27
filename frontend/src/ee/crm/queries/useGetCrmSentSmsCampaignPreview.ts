import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmSentSmsCampaignPreview = (organizerId: IdParam | undefined, campaignId: IdParam | undefined, enabled: boolean) => {
    return useQuery({
        queryKey: crmKeys.campaignPreview(organizerId, campaignId),
        queryFn: async () => {
            const {data} = await crmClient.smsCampaignPreview(organizerId, campaignId);
            return data;
        },
        enabled: !!organizerId && !!campaignId && enabled,
        staleTime: 0,
        retry: false,
    });
};
