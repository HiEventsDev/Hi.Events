import {useMutation} from "@tanstack/react-query";
import {Account} from "../types.ts";
import {accountClient} from "../api/account.client.ts";

export const useUpdateAccount = () => {
    return useMutation(
        ({accountData}: {
            accountData: Account,
        }) => accountClient.updateAccount(accountData),
    )
}
