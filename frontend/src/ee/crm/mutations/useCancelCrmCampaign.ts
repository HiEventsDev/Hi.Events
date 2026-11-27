import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useCancelCrmCampaign = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, campaignId}: {organizerId: IdParam, campaignId: IdParam}) => crmClient.cancelCampaign(organizerId, campaignId),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.campaign(variables.organizerId, variables.campaignId), crmKeys.campaignLists(variables.organizerId)]),
    });
};
