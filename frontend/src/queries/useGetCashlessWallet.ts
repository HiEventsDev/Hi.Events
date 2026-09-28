import {useQuery} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam} from "../types.ts";

export const GET_CASHLESS_WALLET_QUERY_KEY = 'getCashlessWallet';

export const useGetCashlessWallet = (eventId: IdParam, walletId: IdParam) => {
    return useQuery({
        queryKey: [GET_CASHLESS_WALLET_QUERY_KEY, eventId, walletId],
        queryFn: async () => {
            const {data} = await cashlessClient.findWalletById(eventId, walletId);
            return data;
        },
        enabled: !!walletId,
    });
};
