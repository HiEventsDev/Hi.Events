import {useMutation, useQueryClient} from "@tanstack/react-query";
import {Account} from "../types.ts";
import {accountClient} from "../api/account.client.ts";
import {GET_ACCOUNT_QUERY_KEY} from "../queries/useGetAccount.ts";

export const useUpdateAccount = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({accountData}: {
            accountData: Partial<Account>,
        }) => accountClient.updateAccount(accountData),
        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_ACCOUNT_QUERY_KEY]}),
    });
}
