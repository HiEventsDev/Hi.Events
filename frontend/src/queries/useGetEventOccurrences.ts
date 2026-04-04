import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const GET_EVENT_OCCURRENCES_QUERY_KEY = 'getEventOccurrences';

export const useGetEventOccurrences = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery({
        queryKey: [GET_EVENT_OCCURRENCES_QUERY_KEY, eventId, pagination],
        queryFn: async () => await eventOccurrenceClient.all(eventId, pagination),
        placeholderData: keepPreviousData,
        staleTime: 30_000,
        enabled: !!eventId,
    });
};
