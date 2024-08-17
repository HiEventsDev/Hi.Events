import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {checkInListClient} from "../api/check-in-list.client.ts";

export const GET_EVENT_CHECK_IN_LIST_QUERY_KEY = 'getEventCheckInList';

export const useGetEventCheckInList = (checkInListId: IdParam, eventId: IdParam) => {
    return useQuery(
        [GET_EVENT_CHECK_IN_LIST_QUERY_KEY, eventId, checkInListId],
        async () => {
            const {data} = await checkInListClient.get(eventId, checkInListId);
            return data;
        }
    )
};
