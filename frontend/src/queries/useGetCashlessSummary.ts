import {useQuery} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam} from "../types.ts";

export const GET_CASHLESS_SUMMARY_QUERY_KEY = 'getCashlessSummary';

export const useGetCashlessSummary = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_CASHLESS_SUMMARY_QUERY_KEY, eventId],
        queryFn: async () => {
            const {data} = await cashlessClient.getSummary(eventId);
            return data;
        },
        enabled: !!eventId,
    });
};
