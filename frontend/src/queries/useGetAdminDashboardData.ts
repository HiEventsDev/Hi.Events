import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {adminClient, AdminDashboardPeriod} from "../api/admin.client";

export const useGetAdminDashboardData = (period: AdminDashboardPeriod) => {
    return useQuery({
        queryKey: ['admin', 'dashboard', period],
        queryFn: () => adminClient.getDashboardData(period),
        placeholderData: keepPreviousData,
    });
};
