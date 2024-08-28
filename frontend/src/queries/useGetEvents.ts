import {useQuery} from "@tanstack/react-query";
import {eventsClient} from "../api/event.client.ts";
import {QueryFilters} from "../types.ts";

export const GET_EVENTS_QUERY_KEY = 'getEvents';

export const useGetEvents = (pagination: QueryFilters) => {
    return useQuery({
        queryKey: [GET_EVENTS_QUERY_KEY, pagination],

        queryFn: async () => {
            return await eventsClient.all(pagination);
        }
    });
};