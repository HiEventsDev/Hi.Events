import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {orderClient} from "../api/order.client.ts";
import {GET_ORDER_QUERY_KEY} from "../queries/useGetOrder.ts";
import {GET_EVENT_ORDERS_QUERY_KEY} from "../queries/useGetEventOrders.ts";

export const useCancelOrder = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({eventId, orderId}: {
            eventId: IdParam,
            orderId: IdParam,
        }) => orderClient.cancel(eventId, orderId),
        {
            onSuccess: (_, variables) => {
                queryClient.invalidateQueries([GET_ORDER_QUERY_KEY, variables.orderId]);
                queryClient.invalidateQueries([GET_EVENT_ORDERS_QUERY_KEY, variables.eventId]);
            }
        }
    )
}