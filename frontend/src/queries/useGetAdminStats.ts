import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";

export const useGetAdminStats = () => {
    return useQuery({
        queryKey: ['admin', 'stats'],
        queryFn: () => adminClient.getStats(),
    });
};
