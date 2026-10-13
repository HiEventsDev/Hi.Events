import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {adminClient, AdminDashboardPeriod} from "../api/admin.client";

export const useGetAdminEmailDeliveryStats = (period: AdminDashboardPeriod) => {
    return useQuery({
        queryKey: ['admin', 'email-deliveries', 'stats', period],
        queryFn: () => adminClient.getEmailDeliveryStats(period),
        placeholderData: keepPreviousData,
    });
};
