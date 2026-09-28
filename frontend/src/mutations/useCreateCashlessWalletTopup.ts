import {useMutation, useQueryClient} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {CreateCashlessTopupRequest, IdParam} from "../types.ts";
import {GET_CASHLESS_WALLETS_QUERY_KEY} from "../queries/useGetCashlessWallets.ts";
import {GET_CASHLESS_WALLET_QUERY_KEY} from "../queries/useGetCashlessWallet.ts";
import {GET_CASHLESS_TRANSACTIONS_QUERY_KEY} from "../queries/useGetCashlessTransactions.ts";

export const useCreateCashlessWalletTopup = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, walletId, topupData}: {
            eventId: IdParam,
            walletId: IdParam,
            topupData: CreateCashlessTopupRequest
        }) => cashlessClient.topUpWallet(eventId, walletId, topupData),

        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_WALLETS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_WALLET_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_TRANSACTIONS_QUERY_KEY]}),
        ]),
    });
};
