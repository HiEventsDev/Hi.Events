import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {publicCheckInClient} from "../api/check-in.client.ts";

export const useFindKioskTickets = () => {
    return useMutation({
        mutationFn: ({checkInListShortId, email, lastName}: {
            checkInListShortId: IdParam,
            email: string,
            lastName: string,
        }) => publicCheckInClient.findKioskTickets(checkInListShortId, {email, last_name: lastName}),
    });
}
