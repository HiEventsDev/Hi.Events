import {useQuery} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam, QueryFilters} from "../types.ts";

export const GET_CASHLESS_SALES_POINTS_QUERY_KEY = 'getCashlessSalesPoints';

export const useGetCashlessSalesPoints = (eventId: IdParam, queryFilters: QueryFilters = {}) => {
    return useQuery({
        queryKey: [GET_CASHLESS_SALES_POINTS_QUERY_KEY, eventId, queryFilters],
        queryFn: () => cashlessClient.allSalesPoints(eventId, queryFilters),
    });
};
