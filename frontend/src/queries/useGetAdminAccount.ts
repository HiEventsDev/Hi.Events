import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";

export const GET_ADMIN_ACCOUNT_QUERY_KEY = ['admin', 'account'];

export const useGetAdminAccount = (accountId: IdParam) => {
    return useQuery({
        queryKey: [...GET_ADMIN_ACCOUNT_QUERY_KEY, accountId],
        queryFn: () => adminClient.getAccount(accountId),
        enabled: !!accountId,
    });
};
