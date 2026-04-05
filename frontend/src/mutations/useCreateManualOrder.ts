import {useMutation, useQueryClient} from "@tanstack/react-query";
import {CreateManualOrderPayload, orderClient} from "../api/order.client.ts";
import {IdParam} from "../types.ts";

export const useCreateManualOrder = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, payload}: {
            eventId: IdParam,
            payload: CreateManualOrderPayload,
        }) => orderClient.createManualOrder(eventId, payload),

        onSuccess: () => queryClient.invalidateQueries()
    });
}
