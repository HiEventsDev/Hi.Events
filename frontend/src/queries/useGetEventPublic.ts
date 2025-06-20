import {useQuery} from "@tanstack/react-query";
import {eventsClientPublic} from "../api/event.client.ts";
import {Event, IdParam} from "../types.ts";
import {AxiosError} from "axios";

export const GET_EVENT_PUBLIC_QUERY_KEY = "getEventPublic";

export const getEventPublicQuery = (
    eventId: IdParam,
    promoCode: string | null,
    isPromoCodeValid: boolean
) => ({
    queryKey: [GET_EVENT_PUBLIC_QUERY_KEY, eventId, isPromoCodeValid] as const,
    queryFn: async (): Promise<Event> => {
        const {data} = await eventsClientPublic.findByID(eventId, promoCode);
        return data;
    },
    refetchOnWindowFocus: false,
    retryOnMount: false,
    staleTime: 0,
    retry: false,
});

export const useGetEventPublic = (
    eventId: IdParam,
    enabled = true,
    isPromoCodeValid = false,
    promoCode: string | null = null
) => {
    return useQuery<Event, AxiosError>({
        ...getEventPublicQuery(eventId, promoCode, isPromoCodeValid),
        enabled,
    });
};
