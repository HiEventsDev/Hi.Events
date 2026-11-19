import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmAutomationDraft} from "../api/crm.client.ts";

export const useSendTestCrmAutomation = () => {
    return useMutation({
        mutationFn: ({organizerId, draft}: {organizerId: IdParam, draft: CrmAutomationDraft}) => crmClient.sendTestAutomation(organizerId, draft),
    });
};
