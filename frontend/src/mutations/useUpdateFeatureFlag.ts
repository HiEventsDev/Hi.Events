import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {GET_ADMIN_FEATURE_FLAGS_QUERY_KEY} from "../queries/useGetAdminFeatureFlags";
import {GET_ADMIN_ACCOUNT_FEATURE_FLAGS_QUERY_KEY} from "../queries/useGetAdminAccountFeatureFlags";
import {GET_ME_QUERY_KEY} from "../queries/useGetMe";

export const useUpdateFeatureFlag = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({key, enabledByDefault}: { key: string, enabledByDefault: boolean }) =>
            adminClient.updateFeatureFlag(key, enabledByDefault),
        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: GET_ADMIN_FEATURE_FLAGS_QUERY_KEY}),
            queryClient.invalidateQueries({queryKey: GET_ADMIN_ACCOUNT_FEATURE_FLAGS_QUERY_KEY}),
            queryClient.invalidateQueries({queryKey: [GET_ME_QUERY_KEY]}),
        ]),
    });
};
