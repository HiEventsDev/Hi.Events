import {orderClientPublic} from "../api/order.client.ts";
import {IdParam} from "../types.ts";
import {useMutation} from "@tanstack/react-query";

export const useTransitionOrderToOfflinePaymentPublic = () => {
    return useMutation({
        mutationFn: ({eventId, orderShortId}: {
            eventId: IdParam,
            orderShortId: IdParam,
        }) => {
            return orderClientPublic.transitionToOfflinePayment(eventId, orderShortId);
        }
    });
}
