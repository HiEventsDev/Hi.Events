import {useMutation} from "@tanstack/react-query";
import {orderClient} from "../api/order.client.ts";
import {IdParam} from "../types.ts";
import {GET_EVENT_ORDERS_QUERY_KEY} from "../queries/useGetEventOrders.ts";
import {GET_EVENT_COUNTS_QUERY_KEY} from "../queries/useGetEventCounts.ts";
import {queryClient} from "../utilites/queryClient.ts";

export const useMarkOrderAsPaid = () => {
    return useMutation({
        mutationFn: ({eventId, orderId}: {
            eventId: IdParam,
            orderId: IdParam,
        }) => {
            return orderClient.markAsPaid(eventId, orderId);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: [
                    GET_EVENT_ORDERS_QUERY_KEY,
                ]
            });
            queryClient.invalidateQueries({queryKey: [GET_EVENT_COUNTS_QUERY_KEY]});
        }
    });
}
