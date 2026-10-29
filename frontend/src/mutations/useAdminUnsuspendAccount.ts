import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ADMIN_ACCOUNT_QUERY_KEY} from "../queries/useGetAdminAccount";
import {GET_ALL_ACCOUNTS_QUERY_KEY} from "../queries/useGetAllAccounts";

export const useAdminUnsuspendAccount = (accountId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => adminClient.unsuspendAccount(accountId),
        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: GET_ADMIN_ACCOUNT_QUERY_KEY}),
            queryClient.invalidateQueries({queryKey: GET_ALL_ACCOUNTS_QUERY_KEY}),
        ]),
    });
};
