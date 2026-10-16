import {useQuery} from "@tanstack/react-query";
import {eventsClientPublic} from "../api/event.client.ts";
import {Event, IdParam} from "../types.ts";
import {AxiosError} from "axios";
import {ProductSelection} from "../utilites/widgetConfig.ts";

export const GET_EVENT_PUBLIC_QUERY_KEY = "getEventPublic";

export const getEventPublicQuery = (
    eventId: IdParam,
    promoCode: string | null,
    isPromoCodeValid: boolean,
    eventOccurrenceId?: number | null,
    selection?: ProductSelection,
) => ({
    queryKey: [GET_EVENT_PUBLIC_QUERY_KEY, eventId, promoCode, isPromoCodeValid, eventOccurrenceId, ...(selection ? [selection] : [])] as const,
    queryFn: async (): Promise<Event> => {
        const {data} = await eventsClientPublic.findByID(eventId, promoCode, eventOccurrenceId, selection);
        return data;
    },
    refetchOnWindowFocus: false,
    retryOnMount: false,
    staleTime: 0,
    retry: false,
});

export const WIDGET_EVENT_STALE_TIME = 60 * 1000;

export const useGetEventPublic = (
    eventId: IdParam,
    enabled = true,
    isPromoCodeValid = false,
    promoCode: string | null = null,
    eventOccurrenceId: number | null = null,
    selection?: ProductSelection,
) => {
    return useQuery<Event, AxiosError>({
        ...getEventPublicQuery(eventId, promoCode, isPromoCodeValid, eventOccurrenceId, selection),
        enabled,
    });
};
