import {useQuery} from "@tanstack/react-query";
import {Attendee, IdParam} from "../types.ts";
import {attendeeClientPublic} from "../api/attendee.client.ts";
import {AxiosError} from "axios";

export const GET_ATTENDEE_PUBLIC_QUERY_KEY = 'getAttendeePublic';

export const useGetAttendeePublic = (eventId: IdParam, attendeeShortId: string) => {
    return useQuery<Partial<Attendee>, AxiosError>({
        queryKey: [GET_ATTENDEE_PUBLIC_QUERY_KEY, eventId, attendeeShortId],

        queryFn: async () => {
            const {data} = await attendeeClientPublic.findByShortId(eventId, attendeeShortId);
            return data;
        },

        refetchOnWindowFocus: false,
        retryOnMount: false,
        staleTime: 0
    });
};
