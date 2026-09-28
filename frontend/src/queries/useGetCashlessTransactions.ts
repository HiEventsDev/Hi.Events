import {useQuery} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam, QueryFilters} from "../types.ts";

export const GET_CASHLESS_TRANSACTIONS_QUERY_KEY = 'getCashlessTransactions';

export const useGetCashlessTransactions = (eventId: IdParam, queryFilters: QueryFilters = {}) => {
    return useQuery({
        queryKey: [GET_CASHLESS_TRANSACTIONS_QUERY_KEY, eventId, queryFilters],
        queryFn: () => cashlessClient.allTransactions(eventId, queryFilters),
    });
};
