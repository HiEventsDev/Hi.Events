import {useQuery} from "@tanstack/react-query";
import {waitlistClient} from "../api/waitlist.client.ts";
import {GenericPaginatedResponse, IdParam, QueryFilters, WaitlistEntry} from "../types.ts";

export const GET_EVENT_WAITLIST_ENTRIES_QUERY_KEY = 'getEventWaitlistEntries';

export const useGetEventWaitlistEntries = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<WaitlistEntry>>({
        queryKey: [GET_EVENT_WAITLIST_ENTRIES_QUERY_KEY, eventId, pagination],
        queryFn: async () => await waitlistClient.all(eventId, pagination),
    });
};
