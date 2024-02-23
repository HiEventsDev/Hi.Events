import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types";
import {userClient} from "../api/user.client";
import {GET_ME_QUERY_KEY} from "../queries/useGetMe";

export const useConfirmEmailAddress = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({userId, token}: { userId: IdParam, token: string }) =>
            userClient.confirmEmailAddress(userId, token),
        {
            onSuccess: () => {
                queryClient.invalidateQueries({queryKey: [GET_ME_QUERY_KEY]});
            },
        }
    );
};