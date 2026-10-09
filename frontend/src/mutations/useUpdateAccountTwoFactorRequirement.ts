import {useMutation, useQueryClient} from "@tanstack/react-query";
import {accountClient} from "../api/account.client.ts";
import {GET_ACCOUNT_QUERY_KEY} from "../queries/useGetAccount.ts";
import {IdParam} from "../types.ts";

export const useUpdateAccountTwoFactorRequirement = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({accountId, required}: { accountId: IdParam, required: boolean }) =>
            accountClient.updateTwoFactorRequirement(accountId, required),
        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_ACCOUNT_QUERY_KEY]}),
    });
};
