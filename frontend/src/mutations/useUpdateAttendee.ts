import {useMutation, useQueryClient} from "@tanstack/react-query";
import {attendeesClient, EditAttendeeRequest} from "../api/attendee.client.ts";
import {IdParam} from "../types.ts";
import {GET_ATTENDEES_QUERY_KEY} from "../queries/useGetAttendees.ts";
import {GET_ATTENDEE_QUERY_KEY} from "../queries/useGetAttendee.ts";

export const useUpdateAttendee = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({attendeeId, attendeeData, eventId}: {
            attendeeId: IdParam,
            eventId: IdParam,
            attendeeData: EditAttendeeRequest,
        }) => attendeesClient.update(eventId, attendeeId, attendeeData),

        onSuccess: (_data, variables) => {
            return Promise.all([
                queryClient.invalidateQueries({queryKey: [GET_ATTENDEES_QUERY_KEY]}),
                queryClient.invalidateQueries({
                    queryKey: [GET_ATTENDEE_QUERY_KEY, variables.eventId, variables.attendeeId]
                })
            ]);
        }
    });
}
