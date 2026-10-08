import {useMutation} from "@tanstack/react-query";
import {useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {orderClient, RefundOrderPayload} from "../api/order.client.ts";
import {GET_EVENT_ORDERS_QUERY_KEY} from "../queries/useGetEventOrders.ts";
import {GET_OCCUPIED_SEATS_QUERY_KEY} from "../ee/seating/queries/useGetOccupiedSeats.ts";

export const useRefundOrder = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, orderId, refundData}: {
            eventId: IdParam,
            orderId: IdParam,
            refundData: RefundOrderPayload
        }) => orderClient.refund(eventId, orderId, refundData),

        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_ORDERS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_OCCUPIED_SEATS_QUERY_KEY]}),
        ])
    });
}