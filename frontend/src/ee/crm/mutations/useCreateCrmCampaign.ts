import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmCampaignRequest} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useCreateCrmCampaign = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, campaign}: {organizerId: IdParam, campaign: CrmCampaignRequest}) => crmClient.createCampaign(organizerId, campaign),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.campaignLists(variables.organizerId)]),
    });
};
