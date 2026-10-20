import {useQuery} from "@tanstack/react-query";
import {IdParam, ProductPriceOccurrenceOverride} from "../types.ts";
import {AxiosError} from "axios";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const GET_PRICE_OVERRIDES_QUERY_KEY = 'getPriceOverrides';

export const useGetPriceOverrides = (eventId: IdParam, occurrenceId: IdParam) => {
    return useQuery<ProductPriceOccurrenceOverride[], AxiosError>({
        queryKey: [GET_PRICE_OVERRIDES_QUERY_KEY, eventId, occurrenceId],
        queryFn: async () => {
            const {data} = await eventOccurrenceClient.getPriceOverrides(eventId, occurrenceId);
            return data;
        },
        staleTime: 30_000,
        enabled: !!occurrenceId,
    });
};
