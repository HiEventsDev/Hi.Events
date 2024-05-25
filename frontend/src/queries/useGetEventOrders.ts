import {useQuery} from "@tanstack/react-query";
import {orderClient} from "../api/order.client.ts";
import {GenericPaginatedResponse, IdParam, Order, QueryFilters} from "../types.ts";

export const GET_EVENT_ORDERS_QUERY_KEY = 'getEventOrders';

export const useGetEventOrders = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<Order>>({
            queryKey: [GET_EVENT_ORDERS_QUERY_KEY, eventId, pagination],
            queryFn: async () => await orderClient.all(eventId, pagination),
        }
    )
};