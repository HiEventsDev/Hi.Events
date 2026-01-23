import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllAccountsParams} from "../api/admin.client";

export const GET_ALL_ACCOUNTS_QUERY_KEY = ['admin', 'accounts'];

export const useGetAllAccounts = (params: GetAllAccountsParams = {}) => {
    return useQuery({
        queryKey: [...GET_ALL_ACCOUNTS_QUERY_KEY, params],
        queryFn: () => adminClient.getAllAccounts(params),
    });
};
