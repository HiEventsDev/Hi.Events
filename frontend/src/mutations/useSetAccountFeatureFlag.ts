import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ADMIN_FEATURE_FLAGS_QUERY_KEY} from "../queries/useGetAdminFeatureFlags";
import {GET_ADMIN_ACCOUNT_FEATURE_FLAGS_QUERY_KEY} from "../queries/useGetAdminAccountFeatureFlags";
import {GET_ME_QUERY_KEY} from "../queries/useGetMe";

export const useSetAccountFeatureFlag = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({accountId, key, enabled}: { accountId: IdParam, key: string, enabled: boolean | null }) =>
            adminClient.setAccountFeatureFlag(accountId, key, enabled),
        onSuccess: (data, {accountId}) => {
            queryClient.setQueryData([...GET_ADMIN_ACCOUNT_FEATURE_FLAGS_QUERY_KEY, String(accountId)], data);

            return Promise.all([
                queryClient.invalidateQueries({queryKey: GET_ADMIN_FEATURE_FLAGS_QUERY_KEY}),
                queryClient.invalidateQueries({queryKey: [GET_ME_QUERY_KEY]}),
            ]);
        },
    });
};
