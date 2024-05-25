import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {userClient} from "../api/user.client.ts";
import {GET_USERS_QUERY_KEY} from "../queries/useGetUsers.ts";

export const useDeleteUserInvitation = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({userId}: {
            userId: IdParam,
        }) => userClient.deleteInvitation(userId),
        {
            onSuccess: () => {
                queryClient.invalidateQueries([GET_USERS_QUERY_KEY])
            }
        }
    )
}