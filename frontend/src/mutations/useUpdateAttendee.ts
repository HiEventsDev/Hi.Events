import {useMutation, useQueryClient} from "@tanstack/react-query";
import {attendeesClient, EditAttendeeRequest} from "../api/attendee.client.ts";
import {IdParam} from "../types.ts";
import {GET_ATTENDEES_QUERY_KEY} from "../queries/useGetAttendees.ts";

export const useUpdateAttendee = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({attendeeId, attendeeData, eventId}: {
            attendeeId: IdParam,
            eventId: IdParam,
            attendeeData: EditAttendeeRequest,
        }) => attendeesClient.update(eventId, attendeeId, attendeeData),
        {
            onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_ATTENDEES_QUERY_KEY]}),
        }
    )
}