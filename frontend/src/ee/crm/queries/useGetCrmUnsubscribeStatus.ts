import {useQuery} from "@tanstack/react-query";
import {crmClient} from "../api/crm.client.ts";

export const GET_CRM_UNSUBSCRIBE_STATUS_QUERY_KEY = 'getCrmUnsubscribeStatus';

export const getCrmUnsubscribeStatusQuery = (token: string | undefined) => ({
    queryKey: [GET_CRM_UNSUBSCRIBE_STATUS_QUERY_KEY, token],
    queryFn: async () => (await crmClient.unsubscribeStatus(token as string)).data,
    retry: false,
});

export const useGetCrmUnsubscribeStatus = (token: string | undefined, enabled = true) => useQuery({
    ...getCrmUnsubscribeStatusQuery(token),
    enabled: !!token && enabled,
});
