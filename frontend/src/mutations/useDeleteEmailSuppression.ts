import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {adminClient} from "../api/admin.client.ts";
import {GET_EMAIL_SUPPRESSIONS_QUERY_KEY} from "../queries/useGetEmailSuppressions.ts";

export const useDeleteEmailSuppression = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({id}: { id: IdParam }) => adminClient.deleteEmailSuppression(id),

        onSuccess: () => {
            return queryClient.invalidateQueries({queryKey: GET_EMAIL_SUPPRESSIONS_QUERY_KEY});
        }
    });
};
