import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmCampaignRequest} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useUpdateCrmCampaign = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, campaignId, campaign}: {organizerId: IdParam, campaignId: IdParam, campaign: CrmCampaignRequest}) => crmClient.updateCampaign(organizerId, campaignId, campaign),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.campaign(variables.organizerId, variables.campaignId), crmKeys.campaignLists(variables.organizerId)]),
    });
};
