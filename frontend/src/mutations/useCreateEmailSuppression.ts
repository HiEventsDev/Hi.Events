import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient, CreateEmailSuppressionData} from "../api/admin.client.ts";
import {GET_EMAIL_SUPPRESSIONS_QUERY_KEY} from "../queries/useGetEmailSuppressions.ts";

export const useCreateEmailSuppression = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: CreateEmailSuppressionData) => adminClient.createEmailSuppression(data),

        onSuccess: () => {
            return queryClient.invalidateQueries({queryKey: GET_EMAIL_SUPPRESSIONS_QUERY_KEY});
        }
    });
};
