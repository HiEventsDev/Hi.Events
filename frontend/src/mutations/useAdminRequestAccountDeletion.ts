import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient, AdminRequestAccountDeletionPayload} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ALL_DELETION_REQUESTS_QUERY_KEY} from "../queries/useGetAllDeletionRequests";
import {GET_ADMIN_ACCOUNT_QUERY_KEY} from "../queries/useGetAdminAccount";

export const useAdminRequestAccountDeletion = (accountId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: AdminRequestAccountDeletionPayload) =>
            adminClient.requestAccountDeletion(accountId, payload),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_ALL_DELETION_REQUESTS_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: GET_ADMIN_ACCOUNT_QUERY_KEY});
        }
    });
};
