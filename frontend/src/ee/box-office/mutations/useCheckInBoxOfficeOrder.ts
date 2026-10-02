import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";
import {GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY} from "../../../queries/useGetCheckInListAttendeesPublic.ts";
import {GET_CHECK_IN_LIST_PUBLIC_QUERY_KEY} from "../../../queries/useGetCheckInListPublic.ts";
import {GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY} from "../queries/useGetBoxOfficeOrdersPublic.ts";

export const useCheckInBoxOfficeOrder = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({checkInListShortId, attendeePublicIds}: {
            checkInListShortId: IdParam,
            attendeePublicIds: string[],
            boxOfficeShortId: IdParam,
        }) => publicBoxOfficeClient.checkInAttendees(checkInListShortId, attendeePublicIds),

        onSuccess: (_, variables) => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY, variables.checkInListShortId]}),
            queryClient.invalidateQueries({queryKey: [GET_CHECK_IN_LIST_PUBLIC_QUERY_KEY, variables.checkInListShortId]}),
            queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY, variables.boxOfficeShortId]}),
        ]),
    });
}
