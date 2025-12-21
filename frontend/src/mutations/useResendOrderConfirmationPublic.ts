import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {selfServiceClient} from "../api/self-service.client.ts";

export const useResendOrderConfirmationPublic = () => {
    return useMutation({
        mutationFn: ({eventId, orderShortId}: {
            eventId: IdParam;
            orderShortId: string;
        }) => selfServiceClient.resendOrderConfirmation(eventId, orderShortId)
    });
}
