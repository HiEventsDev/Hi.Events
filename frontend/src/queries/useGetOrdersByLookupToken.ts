import {useQuery} from "@tanstack/react-query";
import {ticketLookupClient} from "../api/ticket-lookup.client.ts";
import {Order} from "../types.ts";
import {AxiosError} from "axios";

export const GET_ORDERS_BY_LOOKUP_TOKEN_QUERY_KEY = "getOrdersByLookupToken";

export const useGetOrdersByLookupToken = (token: string | undefined) => {
    return useQuery<Order[], Error>({
        queryKey: [GET_ORDERS_BY_LOOKUP_TOKEN_QUERY_KEY, token],
        queryFn: async () => {
            if (!token) {
                throw new Error("Token is required");
            }
            try {
                const {data} = await ticketLookupClient.getOrdersByToken(token);
                return data;
            } catch (e) {
                const axiosError = e as AxiosError<{ message?: string }>;
                const message = axiosError.response?.data?.message;
                throw new Error(message || "An error occurred");
            }
        },
        enabled: !!token,
        refetchOnWindowFocus: false,
        retry: false,
    });
};
