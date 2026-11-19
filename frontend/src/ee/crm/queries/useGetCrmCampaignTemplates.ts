import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";
import {retryUnlessRejected} from "./retryUnlessRejected.ts";

export const useGetCrmCampaignTemplates = (organizerId: IdParam | undefined, enabled = true) => useQuery({
    queryKey: crmKeys.campaignTemplates(organizerId),
    queryFn: async () => await crmClient.campaignTemplates(organizerId),
    enabled: !!organizerId && enabled,
    retry: retryUnlessRejected,
});
