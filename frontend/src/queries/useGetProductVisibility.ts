import {useQuery} from "@tanstack/react-query";
import {IdParam, ProductOccurrenceVisibility} from "../types.ts";
import {AxiosError} from "axios";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const GET_PRODUCT_VISIBILITY_QUERY_KEY = 'getProductVisibility';

export const useGetProductVisibility = (eventId: IdParam, occurrenceId: IdParam) => {
    return useQuery<ProductOccurrenceVisibility[], AxiosError>({
        queryKey: [GET_PRODUCT_VISIBILITY_QUERY_KEY, eventId, occurrenceId],
        queryFn: async () => {
            const {data} = await eventOccurrenceClient.getProductVisibility(eventId, occurrenceId);
            return data;
        },
        staleTime: 30_000,
        enabled: !!occurrenceId,
    });
};
