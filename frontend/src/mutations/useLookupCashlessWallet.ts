import {useMutation} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam} from "../types.ts";

export const useLookupCashlessWallet = () => {
    return useMutation({
        mutationFn: ({eventId, attendeePublicId}: { eventId: IdParam, attendeePublicId: string }) =>
            cashlessClient.lookupWallet(eventId, attendeePublicId),
    });
};
