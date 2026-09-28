import {useMutation, useQueryClient} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam} from "../types.ts";
import {GET_CASHLESS_SALES_POINTS_QUERY_KEY} from "../queries/useGetCashlessSalesPoints.ts";

export const useDeleteCashlessSalesPoint = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, salesPointId}: { eventId: IdParam, salesPointId: IdParam }) =>
            cashlessClient.deleteSalesPoint(eventId, salesPointId),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_CASHLESS_SALES_POINTS_QUERY_KEY]}),
    });
};
