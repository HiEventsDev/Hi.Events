import {useQuery} from "@tanstack/react-query";
import {eventsClient} from "../api/event.client.ts";
import {QueryFilters} from "../types.ts";

export const GET_EVENTS_QUERY_KEY = 'getEvents';

export const useGetEvents = (pagination: QueryFilters) => {
    return useQuery(
        [GET_EVENTS_QUERY_KEY, pagination],
        async () => {
            return await eventsClient.all(pagination);
        }
    )
};