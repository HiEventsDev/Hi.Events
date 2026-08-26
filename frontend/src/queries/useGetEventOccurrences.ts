import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const GET_EVENT_OCCURRENCES_QUERY_KEY = 'getEventOccurrences';

interface UseGetEventOccurrencesOptions {
    includeStats?: boolean;
}

export const useGetEventOccurrences = (
    eventId: IdParam,
    pagination: QueryFilters,
    enabled: boolean = true,
    options: UseGetEventOccurrencesOptions = {},
) => {
    return useQuery({
        queryKey: [GET_EVENT_OCCURRENCES_QUERY_KEY, eventId, pagination, options.includeStats ?? true],
        queryFn: async () => await eventOccurrenceClient.all(eventId, pagination, options),
        placeholderData: keepPreviousData,
        staleTime: 30_000,
        enabled: !!eventId && enabled,
    });
};
