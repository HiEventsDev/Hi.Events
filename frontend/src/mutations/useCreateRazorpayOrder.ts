import { useMutation } from "@tanstack/react-query"
import { orderClientPublic } from "../api/order.client";
import { IdParam } from "../types";

export const useCreateRazorpayOrder = () => {
    return useMutation({
        mutationFn: async({
            eventId,
            orderShortId
        }: {
            eventId: IdParam,
            orderShortId: IdParam
        }) => {
            const {razorpay_order_id, key_id, amount, currency} = await orderClientPublic.createRazorpayOrder(
                Number(eventId),
                String(orderShortId)
            );
            return {razorpay_order_id, key_id, amount, currency};
        }
    });
}