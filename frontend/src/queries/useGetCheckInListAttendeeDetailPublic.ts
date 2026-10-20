import {useQuery} from "@tanstack/react-query";
import {publicCheckInClient} from "../api/check-in.client";
import {IdParam} from "../types.ts";

export const GET_CHECK_IN_LIST_ATTENDEE_DETAIL_PUBLIC_QUERY_KEY = "getCheckInListAttendeeDetailPublic";

export const useGetCheckInListAttendeeDetailPublic = (
    checkInListShortId: IdParam,
    attendeePublicId: IdParam | null,
) => {
    return useQuery({
        queryKey: [GET_CHECK_IN_LIST_ATTENDEE_DETAIL_PUBLIC_QUERY_KEY, checkInListShortId, attendeePublicId],
        queryFn: () => publicCheckInClient.getCheckInListAttendeeDetail(checkInListShortId, attendeePublicId!),
        enabled: !!checkInListShortId && !!attendeePublicId,
        retry: false,
    });
};
