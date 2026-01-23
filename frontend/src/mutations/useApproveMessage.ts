import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ADMIN_MESSAGES_QUERY_KEY} from "../queries/useGetAdminMessages";

export const useApproveMessage = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (messageId: IdParam) => adminClient.approveMessage(messageId),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: GET_ADMIN_MESSAGES_QUERY_KEY});
        },
    });
};
