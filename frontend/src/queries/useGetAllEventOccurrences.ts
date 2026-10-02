import {useQuery} from "@tanstack/react-query";
import {EventOccurrence, EventOccurrenceStatus, IdParam} from "../types.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

const GET_ALL_EVENT_OCCURRENCES_QUERY_KEY = 'getAllEventOccurrences';

const PAGE_SIZE = 100;

export const firstUpcomingOccurrence = (occurrences: EventOccurrence[]): EventOccurrence | undefined =>
    occurrences.find(occurrence => !occurrence.is_past && occurrence.status !== EventOccurrenceStatus.CANCELLED)
    ?? occurrences[0];

export const useGetAllEventOccurrences = (eventId: IdParam, enabled: boolean) => {
    return useQuery({
        queryKey: [GET_ALL_EVENT_OCCURRENCES_QUERY_KEY, eventId],
        queryFn: async () => {
            const first = await eventOccurrenceClient.all(eventId, {pageNumber: 1, perPage: PAGE_SIZE}, {includeStats: false});
            const rest = await Promise.all(
                Array.from({length: Math.max(0, first.meta.last_page - 1)}, (_, page) =>
                    eventOccurrenceClient.all(eventId, {pageNumber: page + 2, perPage: PAGE_SIZE}, {includeStats: false})),
            );
            return [first, ...rest].flatMap(response => response.data);
        },
        staleTime: 30_000,
        enabled: !!eventId && enabled,
    });
};
