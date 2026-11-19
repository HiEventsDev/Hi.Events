import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmAutomationDraft} from "../api/crm.client.ts";
import {crmKeys} from "./crmQueryKeys.ts";

export const useGetCrmAutomationPreview = (organizerId: IdParam | undefined, draft: CrmAutomationDraft, automationId: number | null) => {
    return useQuery({
        queryKey: crmKeys.automationPreview(organizerId, {...draft, automation_id: automationId}),
        queryFn: async () => {
            const {data} = await crmClient.previewAutomation(organizerId, draft, automationId);
            return data;
        },
        enabled: !!organizerId,
        staleTime: 0,
        gcTime: 0,
        retry: false,
    });
};
