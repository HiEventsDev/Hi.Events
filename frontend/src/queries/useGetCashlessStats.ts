import {useQuery} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam} from "../types.ts";

export const GET_CASHLESS_STATS_QUERY_KEY = 'getCashlessStats';

export const useGetCashlessStats = (eventId: IdParam, startDate?: string, endDate?: string) => {
    return useQuery({
        queryKey: [GET_CASHLESS_STATS_QUERY_KEY, eventId, startDate, endDate],
        queryFn: async () => {
            const {data} = await cashlessClient.getDailyStats(eventId, String(startDate), String(endDate));
            return data;
        },
        enabled: !!eventId && !!startDate && !!endDate,
    });
};
