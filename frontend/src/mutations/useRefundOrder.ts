import {useMutation} from "@tanstack/react-query";
import {useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {orderClient, RefundOrderPayload} from "../api/order.client.ts";
import {GET_EVENT_ORDERS_QUERY_KEY} from "../queries/useGetEventOrders.ts";

export const useRefundOrder = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({eventId, orderId, refundData}: {
            eventId: IdParam,
            orderId: IdParam,
            refundData: RefundOrderPayload
        }) => orderClient.refund(eventId, orderId, refundData),
        {
            onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_EVENT_ORDERS_QUERY_KEY]}),
        }
    )
}