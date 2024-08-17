import {useMutation, useQueryClient} from '@tanstack/react-query';
import {publicCheckInClient} from "../api/check-in.client";
import {GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY} from "../queries/useGetCheckInListAttendeesPublic.ts";
import {IdParam, QueryFilters} from "../types.ts";

export const useCreateCheckInPublic = (pagination: QueryFilters) => {
    const queryClient = useQueryClient();

    return useMutation(
        ({checkInListShortId, attendeePublicId}: { checkInListShortId: IdParam, attendeePublicId: IdParam }) =>
            publicCheckInClient.createCheckIn(checkInListShortId, attendeePublicId),
        {
            onSuccess: (data, {checkInListShortId}) => {
                // Find the attendee in the cache and update the check-in status
                queryClient.setQueryData([GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY, checkInListShortId, pagination], (oldData: any) => {
                        const newAttendees = oldData.data.map((attendee: any) => {
                            if (data?.data?.length && attendee.id === data.data[0].attendee_id) {
                                return {
                                    ...attendee,
                                    check_in: data.data[0],
                                };
                            }
                            return attendee;
                        });

                        return {
                            ...oldData,
                            data: newAttendees,
                        };
                    }
                )
            }
        }
    );
};
