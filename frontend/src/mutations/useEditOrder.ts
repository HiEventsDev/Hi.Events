import {useMutation} from "@tanstack/react-query";
import {IdParam} from '../types';
import {GET_EVENT_ORDERS_QUERY_KEY} from '../queries/useGetEventOrders';
import {EditOrderPayload, orderClient} from '../api/order.client';
import {queryClient} from "../utilites/queryClient.ts";
import {GET_ORDER_QUERY_KEY} from "../queries/useGetOrder.ts";

export const useEditOrder = () => {
    return useMutation({
        mutationFn: ({eventId, orderId, payload}: {
            eventId: IdParam,
            orderId: IdParam,
            payload: EditOrderPayload
        }) => orderClient.editOrder(eventId, orderId, payload),
        onSuccess: (_, variables) => {
            return Promise.all(
                [
                    queryClient.invalidateQueries({
                        queryKey: [GET_EVENT_ORDERS_QUERY_KEY, variables.eventId]
                    }),
                    queryClient.invalidateQueries({
                        queryKey: [GET_ORDER_QUERY_KEY, variables.orderId]
                    })
                ]
            )
        }
    });
}
