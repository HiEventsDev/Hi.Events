import {useMutation} from "@tanstack/react-query";
import {orderClient} from "../api/order.client.ts";
import {IdParam} from "../types.ts";
import {GET_EVENT_ORDERS_QUERY_KEY} from "../queries/useGetEventOrders.ts";
import {queryClient} from "../utilites/queryClient.ts";

export const useRejectOrder = () => {
    return useMutation({
        mutationFn: ({eventId, orderId}: {
            eventId: IdParam,
            orderId: IdParam,
        }) => {
            return orderClient.rejectOrder(eventId, orderId);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: [
                    GET_EVENT_ORDERS_QUERY_KEY,
                ]
            });
        }
    });
}
