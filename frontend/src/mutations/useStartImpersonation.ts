import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ME_QUERY_KEY} from "../queries/useGetMe";
import {setAuthToken} from "../utilites/apiClient";

export const useStartImpersonation = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({userId, accountId}: {userId: IdParam, accountId: IdParam}) =>
            adminClient.startImpersonation(userId, accountId),
        onSuccess: (response) => {
            if (response.token) {
                localStorage.setItem('token', response.token);
                setAuthToken(response.token);
            }
            queryClient.invalidateQueries({queryKey: [GET_ME_QUERY_KEY]});
        }
    });
};
