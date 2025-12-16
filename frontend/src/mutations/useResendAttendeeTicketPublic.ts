import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {selfServiceClient} from "../api/self-service.client.ts";

export const useResendAttendeeTicketPublic = () => {
    return useMutation({
        mutationFn: ({eventId, orderShortId, attendeeShortId}: {
            eventId: IdParam;
            orderShortId: string;
            attendeeShortId: string;
        }) => selfServiceClient.resendAttendeeTicket(eventId, orderShortId, attendeeShortId)
    });
}
