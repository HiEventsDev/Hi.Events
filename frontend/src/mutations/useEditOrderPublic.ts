import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {EditOrderData, selfServiceClient} from "../api/self-service.client.ts";

export const useEditOrderPublic = () => {
    return useMutation({
        mutationFn: ({eventId, orderShortId, data}: {
            eventId: IdParam;
            orderShortId: string;
            data: EditOrderData;
        }) => selfServiceClient.editOrder(eventId, orderShortId, data)
    });
}
