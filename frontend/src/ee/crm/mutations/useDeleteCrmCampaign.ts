import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys, invalidateCrm} from "../queries/crmQueryKeys.ts";

export const useDeleteCrmCampaign = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, campaignId}: {organizerId: IdParam, campaignId: IdParam}) => crmClient.deleteCampaign(organizerId, campaignId),

        onSuccess: (_, variables) => {
            queryClient.removeQueries({queryKey: crmKeys.campaign(variables.organizerId, variables.campaignId)});
            void invalidateCrm(queryClient, [crmKeys.campaignLists(variables.organizerId)]);
        },
    });
};
