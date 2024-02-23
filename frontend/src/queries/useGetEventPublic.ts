import {useQuery} from "@tanstack/react-query";
import {eventsClientPublic} from "../api/event.client.ts";
import {Event, IdParam} from "../types.ts";
import {AxiosError} from "axios";

export const GET_EVENT_PUBLIC_QUERY_KEY = 'getEventPublic';

export const useGetEventPublic = (eventId: IdParam, enabled = true, isPromoCodeValid = false, promoCode: null | string = null) => {
    return useQuery<Event, AxiosError>(
        [GET_EVENT_PUBLIC_QUERY_KEY, eventId, isPromoCodeValid],
        async () => {
            const {data} = await eventsClientPublic.findByID(eventId, promoCode);
            return data;
        },
        {
            refetchOnWindowFocus: false,
            retryOnMount: false,
            staleTime: 0,
            retry: false,
            enabled: enabled,
        }
    )
};