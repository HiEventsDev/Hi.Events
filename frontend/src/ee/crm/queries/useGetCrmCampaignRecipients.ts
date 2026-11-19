import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {CrmRecipientFilter} from "../types.ts";
import {crmKeys} from "./crmQueryKeys.ts";
import {retryUnlessRejected} from "./retryUnlessRejected.ts";

export const useGetCrmCampaignRecipients = (
    organizerId: IdParam | undefined,
    campaignId: IdParam | undefined,
    filter: CrmRecipientFilter,
    page: number,
    isSending: boolean,
) => {
    return useQuery({
        queryKey: crmKeys.campaignRecipients(organizerId, campaignId, filter, page),
        queryFn: async () => {
            return await crmClient.campaignRecipients(organizerId, campaignId, filter, page);
        },
        enabled: !!organizerId && !!campaignId,
        retry: retryUnlessRejected,
        placeholderData: keepPreviousData,
        refetchInterval: isSending ? 5000 : false,
    });
};
