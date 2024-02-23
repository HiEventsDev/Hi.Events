import {useMutation} from "@tanstack/react-query";
import {useQueryClient} from "@tanstack/react-query";
import {UpdateUserRequest, userClient} from "../api/user.client.ts";
import {GET_USERS_QUERY_KEY} from "../queries/useGetUsers.ts";
import {IdParam} from "../types.ts";

export const useEditUser = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({userId, userData}: {
            userId: IdParam,
            userData: UpdateUserRequest
        }) => userClient.updateUser(userId, userData),
        {
            onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_USERS_QUERY_KEY]}),
        }
    )
}