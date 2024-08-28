import {useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {checkInListClient} from "../api/check-in-list.client.ts";

export const GET_EVENT_CHECK_IN_LISTS_QUERY_KEY = 'getEventCheckInLists';

export const useGetEventCheckInLists = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery({
        queryKey: [GET_EVENT_CHECK_IN_LISTS_QUERY_KEY, eventId, pagination],

        queryFn: async () => {
            return await checkInListClient.all(eventId, pagination);
        }
    });
};
