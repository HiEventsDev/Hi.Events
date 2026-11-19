import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmCampaignSendCheck = (organizerId: IdParam | undefined, campaignId: IdParam | undefined, isScheduled: boolean) => {
    return useQuery({
        queryKey: crmKeys.campaignSendCheck(organizerId, campaignId, isScheduled),
        queryFn: async () => {
            const {data} = await crmClient.campaignSendCheck(organizerId, campaignId, isScheduled);
            return data;
        },
        enabled: !!organizerId && !!campaignId,
        staleTime: 0,
        gcTime: 0,
        retry: false,
    });
};
