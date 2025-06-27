import {useMutation, useQueryClient} from "@tanstack/react-query";
import {GET_ME_QUERY_KEY} from "../queries/useGetMe.ts";
import {userClient} from "../api/user.client.ts";
import {IdParam} from "../types.ts";

export const useConfirmEmailWithCode = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({userId, code}: { userId: IdParam, code: IdParam }) => {
            return userClient.confirmEmailAddressWithCode(userId, code);
        },
        onSuccess: () => {
            return queryClient.invalidateQueries({queryKey: [GET_ME_QUERY_KEY]});
        }
    });
};
