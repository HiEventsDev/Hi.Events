import {useMutation, useQueryClient} from "@tanstack/react-query";
import {crmClient} from "../api/crm.client.ts";
import {GET_CRM_UNSUBSCRIBE_STATUS_QUERY_KEY} from "../queries/useGetCrmUnsubscribeStatus.ts";

export const useCrmUnsubscribe = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (token: string) => crmClient.unsubscribe(token),

        onSuccess: (response, token) => queryClient.setQueryData([GET_CRM_UNSUBSCRIBE_STATUS_QUERY_KEY, token], response.data),
    });
};
