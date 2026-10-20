import {useMutation} from "@tanstack/react-query";
import {InviteUserRequest} from "../types.ts";
import {useQueryClient} from "@tanstack/react-query";
import {userClient} from "../api/user.client.ts";
import {GET_USERS_QUERY_KEY} from "../queries/useGetUsers.ts";
import {GET_TEAM_QUERY_KEY} from "../queries/useGetTeam.ts";
import {GET_ME_QUERY_KEY} from "../queries/useGetMe.ts";

export const useInviteUser = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({inviteUserData}: {
            inviteUserData: InviteUserRequest
        }) => userClient.invite(inviteUserData),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_USERS_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_TEAM_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_ME_QUERY_KEY]});
        }
    });
}