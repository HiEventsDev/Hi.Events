import {useQuery} from "@tanstack/react-query";
import {publicCashlessClient} from "../api/cashless-public.client.ts";
import {IdParam} from "../types.ts";

export const GET_CASHLESS_SALES_POINT_PUBLIC_QUERY_KEY = 'getCashlessSalesPointPublic';

export const useGetCashlessSalesPointPublic = (salesPointShortId: IdParam, sessionToken: string | null) => {
    return useQuery({
        queryKey: [GET_CASHLESS_SALES_POINT_PUBLIC_QUERY_KEY, salesPointShortId, sessionToken],
        queryFn: async () => {
            const {data} = await publicCashlessClient.getSalesPoint(salesPointShortId, sessionToken);
            return data;
        },
        enabled: !!salesPointShortId,
        retry: false,
    });
};
