import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {attendeesClient, EditAttendeeRequest} from "../api/attendee.client.ts";
import {GET_ATTENDEES_QUERY_KEY} from "../queries/useGetAttendees.ts";

export const useModifyAttendee = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({attendeeId, attendeeData, eventId}: {
            attendeeId: IdParam,
            eventId: IdParam,
            attendeeData: Partial<EditAttendeeRequest>,
        }) => attendeesClient.modify(eventId, attendeeId, attendeeData),
        {
            onSuccess: () => queryClient.invalidateQueries([GET_ATTENDEES_QUERY_KEY]),
        }
    )
}