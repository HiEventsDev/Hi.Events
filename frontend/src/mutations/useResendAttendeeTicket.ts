import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {attendeesClient} from "../api/attendee.client.ts";

export const useResendAttendeeTicket = () => {
    return useMutation(
        ({eventId, attendeeId}: {
            eventId: IdParam;
            attendeeId: IdParam;
        }) => attendeesClient.resendTicket(eventId, attendeeId),
    )
}