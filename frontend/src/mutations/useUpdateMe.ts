import {useMutation, useQueryClient} from "@tanstack/react-query";
import {userClient, UserMeRequest} from "../api/user.client.ts";
import {GET_ME_QUERY_KEY} from "../queries/useGetMe.ts";

export const useUpdateMe = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({userData}: {
            userData: Partial<UserMeRequest>,
        }) => userClient.updateMe(userData),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_ME_QUERY_KEY]});
        }
    });
}