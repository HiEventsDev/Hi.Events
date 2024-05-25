import {useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {attendeesClient} from "../api/attendee.client.ts";

export const GET_ATTENDEES_QUERY_KEY = 'getAttendees';

export const useGetAttendees = (eventId: IdParam, queryFilters: QueryFilters) => {
    return useQuery({
        queryKey: [GET_ATTENDEES_QUERY_KEY, queryFilters, eventId],
        queryFn: async () => await attendeesClient.all(eventId, queryFilters),
        keepPreviousData: true,
    })
};