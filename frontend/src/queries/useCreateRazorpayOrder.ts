import {useQuery} from "@tanstack/react-query";
import {orderClientPublic} from "../api/order.client.ts";
import {IdParam} from "../types.ts";

export const GET_RAZORPAY_ORDER_PUBLIC_QUERY_KEY = 'getRazorpayOrderPublic';

export const useCreateRazorpayOrder = (eventId: IdParam, orderShortId: IdParam) => {
    return useQuery({
        queryKey: [GET_RAZORPAY_ORDER_PUBLIC_QUERY_KEY, eventId, orderShortId],

        queryFn: async () => {
            const {razorpay_order_id, key_id, amount, currency} = await orderClientPublic.createRazorpayOrder(
                Number(eventId),
                String(orderShortId),
            );
            return {razorpay_order_id, key_id, amount, currency};
        },

        retry: false,
        staleTime: 0,
        gcTime: 0
    });
}