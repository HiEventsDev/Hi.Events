import {useMutation, useQueryClient} from '@tanstack/react-query';
import {publicCheckInClient} from "../api/check-in.client";
import {GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY} from "../queries/useGetCheckInListAttendeesPublic.ts";
import {IdParam, QueryFilters} from "../types.ts";

export const useDeleteCheckInPublic = (pagination: QueryFilters) => {
    const queryClient = useQueryClient();

    return useMutation(
        ({checkInListShortId, checkInShortId}: { checkInListShortId: IdParam, checkInShortId: IdParam }) =>
            publicCheckInClient.deleteCheckIn(checkInListShortId, checkInShortId),
        {
            onSettled: (_, error,  {checkInListShortId, checkInShortId}) => {
                if (error && error.response.status !== 409) {
                    return;
                }

                // Find the attendee in the cache and remove the check-in status
                queryClient.setQueryData([GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY, checkInListShortId, pagination], (oldData: any) => {
                    const newAttendees = oldData?.data?.map((attendee: any) => {
                        if (attendee.check_in?.short_id === checkInShortId) {
                            return {
                                ...attendee,
                                check_in: undefined,
                            };
                        }
                        return attendee;
                    });

                    return {
                        ...oldData,
                        data: newAttendees,
                    };
                });
            },
        }
    );
};
