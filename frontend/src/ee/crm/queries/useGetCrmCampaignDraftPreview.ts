import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmCampaignDraftRequest} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmCampaignDraftPreview = (organizerId: IdParam | undefined, draft: CrmCampaignDraftRequest, enabled: boolean) => {
    return useQuery({
        queryKey: crmKeys.campaignDraftPreview(organizerId, draft),
        queryFn: async () => {
            const {data} = await crmClient.previewCampaignDraft(organizerId, draft);
            return data;
        },
        enabled: !!organizerId && enabled,
        placeholderData: keepPreviousData,
        staleTime: 60_000,
        gcTime: 60_000,
        retry: false,
    });
};
