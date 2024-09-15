import {useQuery} from "@tanstack/react-query";
import {orderClientPublic} from "../api/order.client.ts";
import {IdParam, Order} from "../types.ts";

export const GET_ORDER_PUBLIC_QUERY_KEY = 'getOrderPublic';

export const useGetOrderPublic = (eventId: IdParam, orderShortId: IdParam, includes: string[] = []) => {
    return useQuery<Order>({
        queryKey: [GET_ORDER_PUBLIC_QUERY_KEY, eventId, orderShortId],

        queryFn: async () => {
            const {data} = await orderClientPublic.findByShortId(
                Number(eventId),
                String(orderShortId),
                includes,
            );
            return data;
        },

        refetchOnWindowFocus: false,
        staleTime: 0,
        retryOnMount: false,
        retry: false
    });
}
