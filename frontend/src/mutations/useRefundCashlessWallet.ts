import {useMutation, useQueryClient} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam} from "../types.ts";
import {GET_CASHLESS_WALLETS_QUERY_KEY} from "../queries/useGetCashlessWallets.ts";
import {GET_CASHLESS_WALLET_QUERY_KEY} from "../queries/useGetCashlessWallet.ts";
import {GET_CASHLESS_TRANSACTIONS_QUERY_KEY} from "../queries/useGetCashlessTransactions.ts";

export const useRefundCashlessWallet = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, walletId, method}: {
            eventId: IdParam,
            walletId: IdParam,
            method: 'ORIGINAL_PAYMENT' | 'CASH'
        }) => cashlessClient.refundWallet(eventId, walletId, method),

        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_WALLETS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_WALLET_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_TRANSACTIONS_QUERY_KEY]}),
        ]),
    });
};
