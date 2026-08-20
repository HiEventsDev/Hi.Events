import {useQuery} from "@tanstack/react-query";
import {EventOccurrence, IdParam} from "../types.ts";
import {AxiosError} from "axios";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const GET_EVENT_OCCURRENCE_QUERY_KEY = 'getEventOccurrence';

export const useGetEventOccurrence = (eventId: IdParam, occurrenceId: IdParam) => {
    return useQuery<EventOccurrence, AxiosError>({
        queryKey: [GET_EVENT_OCCURRENCE_QUERY_KEY, eventId, occurrenceId],
        queryFn: async () => {
            const {data} = await eventOccurrenceClient.get(eventId, occurrenceId);
            return data;
        },
        staleTime: 30_000,
        enabled: !!occurrenceId,
    });
};
