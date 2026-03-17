import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";

export const GET_EVENT_DELETION_STATUS_QUERY_KEY = 'getEventDeletionStatus';

export const useGetEventDeletionStatus = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_EVENT_DELETION_STATUS_QUERY_KEY, eventId],

        staleTime: 0,
        queryFn: async () => {
            const {data} = await eventsClient.getDeletionStatus(eventId);
            return data;
        }
    });
};
