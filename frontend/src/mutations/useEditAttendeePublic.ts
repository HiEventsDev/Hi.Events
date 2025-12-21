import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {EditAttendeeData, selfServiceClient} from "../api/self-service.client.ts";

export const useEditAttendeePublic = () => {
    return useMutation({
        mutationFn: ({eventId, orderShortId, attendeeShortId, data}: {
            eventId: IdParam;
            orderShortId: string;
            attendeeShortId: string;
            data: EditAttendeeData;
        }) => selfServiceClient.editAttendee(eventId, orderShortId, attendeeShortId, data)
    });
}
