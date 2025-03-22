import {useQuery} from "@tanstack/react-query";
import {orderClientPublic} from "../api/order.client.ts";
import {IdParam, Order} from "../types.ts";
import {useMemo} from "react";
import {isSsr} from "../utilites/helpers.ts";

export const GET_ORDER_PUBLIC_QUERY_KEY = "getOrderPublic";

const getSessionIdentifierFromUrl = (): string | null => {
    if (isSsr()) return null;

    const url = new URL(window.location.href);
    return url.searchParams.get("session_identifier");
};

export const useGetOrderPublic = (
    eventId: IdParam,
    orderShortId: IdParam,
    includes: string[] = []
) => {
    const sessionIdentifier = useMemo(getSessionIdentifierFromUrl, []);

    return useQuery<Order>({
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
                includes,
                sessionIdentifier ?? undefined
            );
            return data;
        },
        refetchOnWindowFocus: false,
        staleTime: 500,
        retryOnMount: false,
        retry: false,
    });
};
