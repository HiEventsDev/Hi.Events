import {useQuery} from '@tanstack/react-query';
import {Attendee, GenericPaginatedResponse, IdParam} from '../types';
import {publicCheckInClient} from "../api/check-in.client";

export const GET_CHECK_IN_LIST_ATTENDEE_PUBLIC_QUERY_KEY = 'getCheckInListAttendee';

export const useGetCheckInListAttendee = (checkInListShortId: IdParam, attendeePublicId: IdParam) => {
    return useQuery<GenericPaginatedResponse<Attendee>>({
        queryKey: [GET_CHECK_IN_LIST_ATTENDEE_PUBLIC_QUERY_KEY, checkInListShortId, attendeePublicId],
        queryFn: async () => {
            return await publicCheckInClient.getCheckInListAttendee(checkInListShortId, attendeePublicId);
        },
    });
};
