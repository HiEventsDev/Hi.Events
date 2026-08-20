import {useQuery} from "@tanstack/react-query";
import {AxiosError} from "axios";
import {orderClientPublic} from "../api/order.client.ts";
import {IdParam, Order} from "../types.ts";
import {useMemo} from "react";
import {getCheckoutSessionIdentifier} from "../utilites/checkoutSession.ts";

export const GET_ORDER_PUBLIC_QUERY_KEY = "getOrderPublic";

export const useGetOrderPublic = (
    eventId: IdParam,
    orderShortId: IdParam,
    includes: string[] = []
) => {
    const sessionIdentifier = useMemo(
        () => getCheckoutSessionIdentifier(String(orderShortId)),
        [orderShortId]
    );

    return useQuery<Order, AxiosError>({
        queryKey: [
            GET_ORDER_PUBLIC_QUERY_KEY,
            eventId,
            orderShortId,
            sessionIdentifier,
        ],
        queryFn: async () => {
            const {data} = await orderClientPublic.findByShortId(
                Number(eventId),
                String(orderShortId),
                includes
            );
            return data;
        },
        refetchOnWindowFocus: false,
        staleTime: 500,
        retryOnMount: false,
        retry: false,
    });
};
