import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";

export const GET_ADMIN_ORDER_QUERY_KEY = 'getAdminOrder';

export const useGetAdminOrder = (orderId?: IdParam) => {
    return useQuery({
        queryKey: [GET_ADMIN_ORDER_QUERY_KEY, orderId],
        queryFn: async () => await adminClient.getOrder(orderId!),
        enabled: !!orderId,
    });
};
