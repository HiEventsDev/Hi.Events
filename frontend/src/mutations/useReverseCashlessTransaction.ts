import {useMutation, useQueryClient} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam} from "../types.ts";
import {GET_CASHLESS_TRANSACTIONS_QUERY_KEY} from "../queries/useGetCashlessTransactions.ts";
import {GET_CASHLESS_WALLETS_QUERY_KEY} from "../queries/useGetCashlessWallets.ts";
import {GET_CASHLESS_WALLET_QUERY_KEY} from "../queries/useGetCashlessWallet.ts";

export const useReverseCashlessTransaction = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, transactionId, notes}: {
            eventId: IdParam,
            transactionId: IdParam,
            notes?: string
        }) => cashlessClient.reverseTransaction(eventId, transactionId, notes),

        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_TRANSACTIONS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_WALLETS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_WALLET_QUERY_KEY]}),
        ]),
    });
};
