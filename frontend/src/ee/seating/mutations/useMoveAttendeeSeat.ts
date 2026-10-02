import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {seatMapClient} from "../api/seat-map.client.ts";
import {GET_OCCUPIED_SEATS_QUERY_KEY} from "../queries/useGetOccupiedSeats.ts";
import {GET_SEAT_AVAILABILITY_QUERY_KEY} from "../queries/useGetSeatAvailability.ts";
import {GET_ATTENDEE_QUERY_KEY} from "../../../queries/useGetAttendee.ts";
import {GET_ATTENDEES_QUERY_KEY} from "../../../queries/useGetAttendees.ts";

export const useMoveAttendeeSeat = (eventId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({attendeeId, seatUid}: {attendeeId: IdParam; seatUid: string}) =>
            seatMapClient.moveAttendeeSeat(eventId, attendeeId, seatUid),

        onSettled: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_OCCUPIED_SEATS_QUERY_KEY, eventId]}),
            queryClient.invalidateQueries({queryKey: [GET_SEAT_AVAILABILITY_QUERY_KEY, eventId]}),
            queryClient.invalidateQueries({queryKey: [GET_ATTENDEE_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_ATTENDEES_QUERY_KEY]}),
        ]),
    });
};
