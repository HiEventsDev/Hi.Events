import {useMutation, useQueryClient} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam} from "../types.ts";
import {GET_CASHLESS_SUMMARY_QUERY_KEY} from "../queries/useGetCashlessSummary.ts";
import {GET_CASHLESS_SETTINGS_QUERY_KEY} from "../queries/useGetCashlessSettings.ts";
import {GET_CASHLESS_WALLETS_QUERY_KEY} from "../queries/useGetCashlessWallets.ts";
import {GET_CASHLESS_TRANSACTIONS_QUERY_KEY} from "../queries/useGetCashlessTransactions.ts";
import {GET_CASHLESS_STATS_QUERY_KEY} from "../queries/useGetCashlessStats.ts";
import {GET_EVENT_STATS_QUERY_KEY} from "../queries/useGetEventStats.ts";

export const useCloseCashless = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId}: { eventId: IdParam }) => cashlessClient.closeCashless(eventId),

        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_SUMMARY_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_SETTINGS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_WALLETS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_TRANSACTIONS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_CASHLESS_STATS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_STATS_QUERY_KEY]}),
        ]),
    });
};
