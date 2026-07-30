import {useMutation, useQueryClient} from "@tanstack/react-query";
import {accountClient} from "../api/account.client.ts";
import {GET_ACCOUNT_QUERY_KEY} from "../queries/useGetAccount.ts";
import {GET_ACCOUNT_DELETION_STATUS_QUERY_KEY} from "../queries/useGetAccountDeletionStatus.ts";

export const useCancelAccountDeletion = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => accountClient.cancelDeletion(),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_ACCOUNT_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_ACCOUNT_DELETION_STATUS_QUERY_KEY]});
        }
    });
}
