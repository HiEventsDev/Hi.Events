import {useMutation, useQueryClient} from "@tanstack/react-query";
import {userClient} from "../api/user.client.ts";
import {GET_USERS_QUERY_KEY} from "../queries/useGetUsers.ts";
import {IdParam} from "../types.ts";

export const useResetAccountUserTwoFactor = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (userId: IdParam) => userClient.resetTwoFactor(userId),
        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_USERS_QUERY_KEY]}),
    });
};
