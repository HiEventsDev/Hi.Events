import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAdminDashboardParams} from "../api/admin.client";

export const useGetAdminDashboardData = (params: GetAdminDashboardParams = {}) => {
    return useQuery({
        queryKey: ['admin', 'dashboard', params.days, params.limit],
        queryFn: () => adminClient.getDashboardData(params),
    });
};
