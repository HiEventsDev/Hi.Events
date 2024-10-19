import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {attendeesClient} from "../api/attendee.client.ts";

export const useResendAttendeeProduct = () => {
    return useMutation({
        mutationFn: ({eventId, attendeeId}: {
            eventId: IdParam;
            attendeeId: IdParam;
        }) => attendeesClient.resendProduct(eventId, attendeeId)
    });
}