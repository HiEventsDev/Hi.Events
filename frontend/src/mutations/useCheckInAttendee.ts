import {useMutation, useQueryClient} from "@tanstack/react-query";
import {Attendee, IdParam, QueryFilters} from "../types.ts";
import {attendeesClient} from "../api/attendee.client.ts";
import {GET_ATTENDEES_QUERY_KEY} from "../queries/useGetAttendees.ts";
import {GET_EVENT_STATS_QUERY_KEY} from "../queries/useGetEventStats.ts";

export const useCheckInAttendee = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, action, attendeePublicId}: {
            eventId: IdParam,
            action: 'check_in' | 'check_out',
            attendeePublicId: string,
            pagination: QueryFilters,
        }) => attendeesClient.checkIn(eventId, attendeePublicId, action),

        onSuccess: (updatedAttendee, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_STATS_QUERY_KEY, variables.eventId]
            });

            const currentAttendees = queryClient.getQueryData<{ data: Attendee[] }>(
                [GET_ATTENDEES_QUERY_KEY, variables.pagination, variables.eventId]
            );

            const currentData = currentAttendees?.data;

            if (currentData) {
                const updatedAttendees = currentData.map(att =>
                    att.id === updatedAttendee.data.id ? updatedAttendee.data : att
                );

                const updatedData = {...currentAttendees, data: updatedAttendees};

                queryClient.setQueryData(
                    [GET_ATTENDEES_QUERY_KEY, variables.pagination, variables.eventId],
                    updatedData
                );
            }
        }
    });
}
