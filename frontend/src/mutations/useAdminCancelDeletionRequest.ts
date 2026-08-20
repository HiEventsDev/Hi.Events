import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ALL_DELETION_REQUESTS_QUERY_KEY} from "../queries/useGetAllDeletionRequests";

export const useAdminCancelDeletionRequest = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (deletionRequestId: IdParam) => adminClient.cancelDeletionRequest(deletionRequestId),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_ALL_DELETION_REQUESTS_QUERY_KEY]});
        }
    });
};
