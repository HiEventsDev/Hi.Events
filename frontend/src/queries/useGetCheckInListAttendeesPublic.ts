import { useQuery } from '@tanstack/react-query';
import {CheckIn, QueryFilters, GenericPaginatedResponse, IdParam, Attendee} from '../types';
import {publicCheckInClient} from "../api/check-in.client";

export const GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY = 'getCheckInListAttendees';

export const useGetCheckInListAttendees = (checkInListShortId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<Attendee>>({
        queryKey: [GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY, checkInListShortId, pagination],
        queryFn: async () => {
            const data = await publicCheckInClient.getCheckInListAttendees(checkInListShortId, pagination);
            return data;
        }
    });
};
