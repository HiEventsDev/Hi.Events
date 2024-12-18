import {useQuery} from "@tanstack/react-query";
import {orderClientPublic} from "../api/order.client.ts";
import {IdParam, Order} from "../types.ts";
import {GET_ORDER_PUBLIC_QUERY_KEY} from "./useGetOrderPublic.ts";

export const usePollGetOrderPublic = (eventId: IdParam, orderShortId: IdParam, enabled: boolean, includes: string[] = []) => {
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

        refetchInterval: 5000,
        enabled: enabled
    });
}
