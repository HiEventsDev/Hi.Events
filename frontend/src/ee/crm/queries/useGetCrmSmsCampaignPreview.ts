import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmSmsPreviewRequest} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmSmsCampaignPreview = (organizerId: IdParam | undefined, draft: CrmSmsPreviewRequest, enabled: boolean) => {
    return useQuery({
        queryKey: crmKeys.campaignSmsPreview(organizerId, draft),
        queryFn: async () => {
            const {data} = await crmClient.previewSmsCampaign(organizerId, draft);
            return data;
        },
        enabled: !!organizerId && enabled,
        placeholderData: keepPreviousData,
        staleTime: 30_000,
        gcTime: 30_000,
        retry: false,
    });
};
