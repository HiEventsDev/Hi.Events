import {useQuery} from "@tanstack/react-query";
import {orderClient} from "../api/order.client.ts";
import {IdParam, Order} from "../types.ts";

export const GET_ORDER_QUERY_KEY = 'getEventOrder';

export const useGetOrder = (eventId: IdParam, orderId: IdParam) => {
    return useQuery<Order, Error>(
        [GET_ORDER_QUERY_KEY, orderId],
        async () => {
            const {data} = await orderClient.findByID(Number(eventId), Number(orderId));
            return data;
        }, {
            enabled: !!eventId && !!orderId,
        },
    );
}