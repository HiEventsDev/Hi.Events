import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";

export const GET_ADMIN_ACCOUNT_FEATURE_FLAGS_QUERY_KEY = ['admin', 'account-feature-flags'];

export const useGetAdminAccountFeatureFlags = (accountId: IdParam) => {
    return useQuery({
        queryKey: [...GET_ADMIN_ACCOUNT_FEATURE_FLAGS_QUERY_KEY, String(accountId)],
        queryFn: () => adminClient.getAccountFeatureFlags(accountId),
        enabled: !!accountId,
    });
};
