import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {orderClient} from "../api/order.client.ts";

export const useResendOrderConfirmation = () => {
    return useMutation(
        ({eventId, orderId}: {
            eventId: IdParam,
            orderId: IdParam,
        }) => orderClient.resendConfirmation(eventId, orderId),
    )
}