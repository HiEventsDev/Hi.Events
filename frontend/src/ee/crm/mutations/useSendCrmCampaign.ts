import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useSendCrmCampaign = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, campaignId, scheduledAt}: {organizerId: IdParam, campaignId: IdParam, scheduledAt: string | null}) => crmClient.sendCampaign(organizerId, campaignId, scheduledAt),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.campaign(variables.organizerId, variables.campaignId), crmKeys.campaignLists(variables.organizerId)]),
    });
};
