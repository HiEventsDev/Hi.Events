import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllAccountsParams} from "../api/admin.client";

export const useGetAllAccounts = (params: GetAllAccountsParams = {}) => {
    return useQuery({
        queryKey: ['admin', 'accounts', params],
        queryFn: () => adminClient.getAllAccounts(params),
    });
};
