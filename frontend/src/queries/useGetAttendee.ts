import {useQuery} from "@tanstack/react-query";
import {Attendee, IdParam} from "../types.ts";
import {attendeesClient} from "../api/attendee.client.ts";

export const GET_ATTENDEE_QUERY_KEY = 'getAttendee';

export const useGetAttendee = (eventId: IdParam, attendeeId: IdParam) => {
    return useQuery<Attendee, Error>(
        [GET_ATTENDEE_QUERY_KEY, eventId, attendeeId],
        async () => {
            const {data} = await attendeesClient.findById(eventId, attendeeId);
            return data;
        },
        {
            staleTime: 0,
            cacheTime: 0,
        }
    )
};