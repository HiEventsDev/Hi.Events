import {useMutation, useQueryClient} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {CashlessWalletStatus, IdParam} from "../types.ts";
import {GET_CASHLESS_WALLETS_QUERY_KEY} from "../queries/useGetCashlessWallets.ts";
import {GET_CASHLESS_WALLET_QUERY_KEY} from "../queries/useGetCashlessWallet.ts";

export const useUpdateCashlessWalletStatus = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, walletId, status}: {
            eventId: IdParam,
            walletId: IdParam,
            status: CashlessWalletStatus
        }) => cashlessClient.updateWalletStatus(eventId, walletId, status),

        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_WALLETS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_WALLET_QUERY_KEY]}),
        ]),
    });
};
