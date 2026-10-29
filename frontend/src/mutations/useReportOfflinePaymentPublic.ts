import {useMutation, useQueryClient} from "@tanstack/react-query";
import {orderClientPublic} from "../api/order.client.ts";
import {IdParam} from "../types.ts";
import {GET_ORDER_PUBLIC_QUERY_KEY} from "../queries/useGetOrderPublic.ts";

export const useReportOfflinePaymentPublic = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, orderShortId, note}: {
            eventId: IdParam,
            orderShortId: IdParam,
            note: string | null,
        }) => orderClientPublic.reportOfflinePayment(eventId, orderShortId, note),
        onSuccess: (_, {eventId, orderShortId}) => queryClient.invalidateQueries({
            queryKey: [GET_ORDER_PUBLIC_QUERY_KEY, String(eventId), String(orderShortId)],
        }),
    });
}
