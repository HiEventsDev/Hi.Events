import {useQuery} from '@tanstack/react-query';
import {Attendee, GenericPaginatedResponse, IdParam, QueryFilters} from '../types';
import {publicCheckInClient} from "../api/check-in.client";

export const GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY = 'getCheckInListAttendees';

export const useGetCheckInListAttendees = (checkInListShortId: IdParam, pagination: QueryFilters, enabled: boolean = true) => {
    return useQuery<GenericPaginatedResponse<Attendee>>({
        queryKey: [GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY, checkInListShortId, pagination],
        queryFn: async () => {
            return await publicCheckInClient.getCheckInListAttendees(checkInListShortId, pagination);
        },
        enabled: enabled,
    });
};
