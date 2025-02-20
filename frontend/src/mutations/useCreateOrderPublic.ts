import {useMutation, useQueryClient} from "@tanstack/react-query";
import {orderClientPublic, ProductFormPayload} from "../api/order.client.ts";
import {IdParam} from "../types.ts";

export const useCreateOrderPublic = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({orderData, eventId}: {
            orderData: ProductFormPayload,
            eventId: IdParam,
        }) => orderClientPublic.create(Number(eventId), orderData),

        onSuccess: () => queryClient.invalidateQueries()
    });
}