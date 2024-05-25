import {useQuery} from "@tanstack/react-query";
import {Attendee, IdParam} from "../types.ts";
import {attendeeClientPublic} from "../api/attendee.client.ts";

export const GET_ATTENDEE_PUBLIC_QUERY_KEY = 'getAttendeePublic';

export const useGetAttendeePublic = (eventId: IdParam, attendeeShortId: string) => {
    return useQuery<Partial<Attendee>, Error>(
        [GET_ATTENDEE_PUBLIC_QUERY_KEY, eventId, attendeeShortId],
        async () => {
            const {data} = await attendeeClientPublic.findByShortId(eventId, attendeeShortId);
            return data;
        },
        {
            refetchOnWindowFocus: false,
            retryOnMount: false,
            staleTime: 0,
        }
    )
};