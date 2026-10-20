import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {GET_ADMIN_FEATURE_FLAGS_QUERY_KEY} from "./useGetAdminFeatureFlags";

export const GET_ADMIN_FEATURE_FLAG_OVERRIDES_QUERY_KEY = [...GET_ADMIN_FEATURE_FLAGS_QUERY_KEY, 'overrides'];

export const useGetAdminFeatureFlagOverrides = (key: string, enabled: boolean) => {
    return useQuery({
        queryKey: [...GET_ADMIN_FEATURE_FLAG_OVERRIDES_QUERY_KEY, key],
        queryFn: () => adminClient.getFeatureFlagOverrides(key),
        enabled,
    });
};
