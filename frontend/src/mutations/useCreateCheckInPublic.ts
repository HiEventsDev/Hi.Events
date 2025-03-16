import {useMutation, useQueryClient} from '@tanstack/react-query';
import {publicCheckInClient} from "../api/check-in.client";
import {GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY} from "../queries/useGetCheckInListAttendeesPublic.ts";
import {IdParam, QueryFilters} from "../types.ts";

export const useCreateCheckInPublic = (pagination: QueryFilters) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({checkInListShortId, attendeePublicId, action}: {
            checkInListShortId: IdParam,
            attendeePublicId: IdParam,
            action: 'check-in' | 'check-in-and-mark-order-as-paid'
        }) =>
            publicCheckInClient.createCheckIn(checkInListShortId, attendeePublicId, action),

        onSuccess: (data, {checkInListShortId, action}) => {
            const markedAsPaid = action === 'check-in-and-mark-order-as-paid';

            queryClient.setQueryData(
                [GET_CHECK_IN_LIST_ATTENDEES_PUBLIC_QUERY_KEY, checkInListShortId, pagination],
                (oldData: any) => {
                    if (!oldData?.data) return oldData;

                    const updatedAttendee = data?.data?.find((checkIn: any) => checkIn.attendee_id);

                    if (!updatedAttendee) return oldData;

                    const updatedOrderId = updatedAttendee.order_id;

                    const newAttendees = oldData.data.map((attendee: any) => {
                        const attendeeCheckIn = data?.data?.find(
                            (checkIn: any) => checkIn.attendee_id === attendee.id
                        );

                        const hasError = data.errors && Object.keys(data.errors).some(
                            (key) => key === attendee.public_id
                        );

                        if (attendeeCheckIn) {
                            return {
                                ...attendee,
                                check_in: attendeeCheckIn,
                                status: markedAsPaid && !hasError ? 'ACTIVE' : attendee.status,
                            };
                        }

                        // Mark all attendees with the same order_id as ACTIVE if markedAsPaid
                        if (markedAsPaid && attendee.order_id === updatedOrderId) {
                            return {
                                ...attendee,
                                status: 'ACTIVE',
                            };
                        }
                        return attendee;
                    });

                    return {
                        ...oldData,
                        data: newAttendees,
                    };
                }
            );
        }
    });
};
