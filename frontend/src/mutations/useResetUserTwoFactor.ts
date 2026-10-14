import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client.ts";
import {GET_ALL_USERS_QUERY_KEY} from "../queries/useGetAllUsers.ts";
import {IdParam} from "../types.ts";

export const useResetUserTwoFactor = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (userId: IdParam) => adminClient.resetUserTwoFactor(userId),
        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_ALL_USERS_QUERY_KEY]}),
    });
};
