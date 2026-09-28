import {useQuery} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam, QueryFilters} from "../types.ts";

export const GET_CASHLESS_WALLETS_QUERY_KEY = 'getCashlessWallets';

export const useGetCashlessWallets = (eventId: IdParam, queryFilters: QueryFilters = {}) => {
    return useQuery({
        queryKey: [GET_CASHLESS_WALLETS_QUERY_KEY, eventId, queryFilters],
        queryFn: () => cashlessClient.allWallets(eventId, queryFilters),
    });
};
