import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {CrmCampaignListFilters} from "../types.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmCampaigns = (organizerId: IdParam | undefined, filters: CrmCampaignListFilters, page: number, enabled = true) => {
    return useQuery({
        queryKey: crmKeys.campaignList(organizerId, filters, page),
        queryFn: async () => {
            return await crmClient.campaigns(organizerId, filters, page);
        },
        enabled: !!organizerId && enabled,
        placeholderData: keepPreviousData,
        refetchInterval: (query) => query.state.data?.data.some(campaign => campaign.status === 'SENDING') ? 5000 : false,
    });
};
