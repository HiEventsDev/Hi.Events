import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";

export const GET_ADMIN_FEATURE_FLAGS_QUERY_KEY = ['admin', 'feature-flags'];

export const useGetAdminFeatureFlags = (enabled = true) => {
    return useQuery({
        queryKey: GET_ADMIN_FEATURE_FLAGS_QUERY_KEY,
        queryFn: () => adminClient.getFeatureFlags(),
        enabled,
    });
};
