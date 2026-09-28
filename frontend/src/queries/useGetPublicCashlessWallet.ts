import {useQuery} from "@tanstack/react-query";
import {publicCashlessClient} from "../api/cashless-public.client.ts";
import {IdParam} from "../types.ts";

export const GET_PUBLIC_CASHLESS_WALLET_QUERY_KEY = 'getPublicCashlessWallet';

export const useGetPublicCashlessWallet = (eventId: IdParam, ticketReference: IdParam) => {
    return useQuery({
        queryKey: [GET_PUBLIC_CASHLESS_WALLET_QUERY_KEY, eventId, ticketReference],
        queryFn: async () => {
            const {data} = await publicCashlessClient.getWallet(eventId, ticketReference);
            return data;
        },
        enabled: !!eventId && !!ticketReference,
        retry: false,
    });
};
