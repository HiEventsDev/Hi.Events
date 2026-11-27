import {useMutation, useQueryClient} from "@tanstack/react-query";
import {crmClient} from "../api/crm.client.ts";
import {CrmAdminCampaign} from "../types.ts";
import {GET_ADMIN_CRM_CAMPAIGNS_QUERY_KEY} from "../queries/useGetAdminCrmCampaigns.ts";

export const useReviewAdminCrmCampaign = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({campaign, decision, note = null}: {campaign: CrmAdminCampaign, decision: 'approve' | 'reject', note?: string | null}) =>
            crmClient.adminReviewCampaign(campaign, decision, note),

        onSettled: () => queryClient.invalidateQueries({queryKey: [GET_ADMIN_CRM_CAMPAIGNS_QUERY_KEY]}),
    });
};
