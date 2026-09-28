import {useQuery} from "@tanstack/react-query";
import {publicCashlessClient} from "../api/cashless-public.client.ts";
import {IdParam} from "../types.ts";

export const GET_CASHLESS_SALES_POINT_TRANSACTIONS_QUERY_KEY = 'getCashlessSalesPointTransactions';

export const useGetCashlessSalesPointTransactions = (salesPointShortId: IdParam, sessionToken: string | null, enabled: boolean) => {
    return useQuery({
        queryKey: [GET_CASHLESS_SALES_POINT_TRANSACTIONS_QUERY_KEY, salesPointShortId],
        queryFn: async () => {
            const {data} = await publicCashlessClient.getSalesPointTransactions(salesPointShortId, sessionToken);
            return data;
        },
        enabled: !!salesPointShortId && enabled,
        retry: false,
    });
};
