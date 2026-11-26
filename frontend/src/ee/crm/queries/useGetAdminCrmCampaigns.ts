import {useQuery} from "@tanstack/react-query";
import {crmClient} from "../api/crm.client.ts";

export const GET_ADMIN_CRM_CAMPAIGNS_QUERY_KEY = 'getAdminCrmCampaigns';

export const useGetAdminCrmCampaigns = () => {
    return useQuery({
        queryKey: [GET_ADMIN_CRM_CAMPAIGNS_QUERY_KEY],
        queryFn: async () => {
            const {data} = await crmClient.adminPendingCampaigns();
            return data;
        },
    });
};
