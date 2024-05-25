import {useMutation, useQueryClient} from '@tanstack/react-query';
import {userClient} from '../api/user.client.ts';
import {IdParam} from '../types.ts';
import {GET_ME_QUERY_KEY} from "../queries/useGetMe.ts";

export const useConfirmEmailChange = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({userId, token}: { userId: IdParam, token: string }) =>
            userClient.confirmEmailChange(userId, token),
        {
            onSuccess: () => {
                queryClient.invalidateQueries({queryKey: [GET_ME_QUERY_KEY]});
            },
        }
    );
};