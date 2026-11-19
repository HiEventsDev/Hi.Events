import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";

export const useSendTestCrmCampaign = () => {
    return useMutation({
        mutationFn: ({organizerId, campaignId, phone}: {organizerId: IdParam, campaignId: IdParam, phone?: string}) =>
            crmClient.sendTestCampaign(organizerId, campaignId, phone),
    });
};
