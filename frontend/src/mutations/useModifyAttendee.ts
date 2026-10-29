import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {attendeesClient, EditAttendeeRequest} from "../api/attendee.client.ts";
import {GET_ATTENDEES_QUERY_KEY} from "../queries/useGetAttendees.ts";
import {GET_EVENT_COUNTS_QUERY_KEY} from "../queries/useGetEventCounts.ts";
import {GET_OCCUPIED_SEATS_QUERY_KEY} from "../ee/seating/queries/useGetOccupiedSeats.ts";
import {GET_SEAT_AVAILABILITY_QUERY_KEY} from "../ee/seating/queries/useGetSeatAvailability.ts";

export const useModifyAttendee = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({attendeeId, attendeeData, eventId}: {
            attendeeId: IdParam,
            eventId: IdParam,
            attendeeData: Partial<EditAttendeeRequest>,
        }) => attendeesClient.modify(eventId, attendeeId, attendeeData),

        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_ATTENDEES_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_COUNTS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_OCCUPIED_SEATS_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_SEAT_AVAILABILITY_QUERY_KEY]}),
        ])
    });
}