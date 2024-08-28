import {useQuery} from "@tanstack/react-query";
import {orderClientPublic} from "../api/order.client.ts";
import {IdParam, StripePaymentIntent} from "../types.ts";

export const GET_ORDER_STRIPE_PAYMENT_INTENT_PUBLIC_QUERY_KEY = 'getOrderStripePaymentIntentPublic';

export const useGetOrderStripePaymentIntentPublic = (eventId: IdParam, orderShortId: IdParam, enabled: boolean) => {
    return useQuery<StripePaymentIntent>({
        queryKey: [GET_ORDER_STRIPE_PAYMENT_INTENT_PUBLIC_QUERY_KEY, eventId, orderShortId],

        queryFn: async () => {
            const {data} = await orderClientPublic.findOrderStripePaymentIntent(Number(eventId), String(orderShortId));
            return data;
        },

        enabled: enabled
    });
}
