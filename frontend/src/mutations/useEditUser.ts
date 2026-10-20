import {useMutation} from "@tanstack/react-query";
import {useQueryClient} from "@tanstack/react-query";
import {UpdateUserRequest, userClient} from "../api/user.client.ts";
import {GET_USERS_QUERY_KEY} from "../queries/useGetUsers.ts";
import {GET_TEAM_QUERY_KEY} from "../queries/useGetTeam.ts";
import {GET_ME_QUERY_KEY} from "../queries/useGetMe.ts";
import {IdParam} from "../types.ts";

export const useEditUser = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({userId, userData}: {
            userId: IdParam,
            userData: UpdateUserRequest
        }) => userClient.updateUser(userId, userData),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_USERS_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_TEAM_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_ME_QUERY_KEY]});
        }
    });
}