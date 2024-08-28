import {useMutation} from "@tanstack/react-query";
import {Account} from "../types.ts";
import {accountClient} from "../api/account.client.ts";

export const useUpdateAccount = () => {
    return useMutation({
        mutationFn: ({accountData}: {
            accountData: Account,
        }) => accountClient.updateAccount(accountData)
    });
}
