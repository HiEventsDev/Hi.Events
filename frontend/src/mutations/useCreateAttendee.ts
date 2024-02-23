import {useMutation} from "@tanstack/react-query";
import {attendeesClient, CreateAttendeeRequest} from "../api/attendee.client.ts";
import {GET_ATTENDEES_QUERY_KEY} from "../queries/useGetAttendees.ts";
import {useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";

export const useCreateAttendee = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({eventId, attendeeData}: {
            eventId: IdParam,
            attendeeData: CreateAttendeeRequest,
        }) => attendeesClient.create(eventId, attendeeData),
        {
            onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_ATTENDEES_QUERY_KEY]}),
        }
    )
}