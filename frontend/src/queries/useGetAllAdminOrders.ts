import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllOrdersParams} from "../api/admin.client";

export const GET_ALL_ORDERS_QUERY_KEY = 'getAllOrders';

export const useGetAllAdminOrders = (params: GetAllOrdersParams = {}) => {
    return useQuery({
        queryKey: [GET_ALL_ORDERS_QUERY_KEY, params],
        queryFn: async () => await adminClient.getAllOrders(params),
    });
};
