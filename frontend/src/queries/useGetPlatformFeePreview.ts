import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsSettingsClient} from "../api/event-settings.client.ts";

export const GET_PLATFORM_FEE_PREVIEW_QUERY_KEY = 'getPlatformFeePreview';

export const useGetPlatformFeePreview = (eventId: IdParam, price: number) => {
    return useQuery({
        queryKey: [GET_PLATFORM_FEE_PREVIEW_QUERY_KEY, eventId, price],
        queryFn: async () => {
            return eventsSettingsClient.getPlatformFeePreview(eventId, price);
        },
        enabled: !!eventId && price > 0,
    });
};
