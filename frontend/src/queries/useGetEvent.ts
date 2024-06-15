import {useQuery} from "@tanstack/react-query";
import {eventsClient} from "../api/event.client.ts";
import {Event, IdParam} from "../types.ts";

export const GET_EVENT_QUERY_KEY = 'getEvent';

export const useGetEvent = (eventId: IdParam) => {
    return useQuery<Event, Error>(
        [GET_EVENT_QUERY_KEY, eventId],
        async () => {
            const {data} = await eventsClient.findByID(eventId);
            return data;
        },
        {
            staleTime: 5,
        }
    );
};