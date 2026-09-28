import {useQuery} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam} from "../types.ts";

export const GET_CASHLESS_SETTINGS_QUERY_KEY = 'getCashlessSettings';

export const useGetCashlessSettings = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_CASHLESS_SETTINGS_QUERY_KEY, eventId],
        queryFn: async () => {
            const {data} = await cashlessClient.getSettings(eventId);
            return data;
        },
        enabled: !!eventId,
    });
};
