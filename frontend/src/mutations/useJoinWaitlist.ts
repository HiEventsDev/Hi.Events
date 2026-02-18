import {useMutation} from "@tanstack/react-query";
import {IdParam, JoinWaitlistRequest} from "../types.ts";
import {waitlistClientPublic} from "../api/waitlist.client.ts";

export const useJoinWaitlist = () => {
    return useMutation({
        mutationFn: ({eventId, data}: {
            eventId: IdParam,
            data: JoinWaitlistRequest,
        }) => waitlistClientPublic.join(eventId, data),
    });
};
