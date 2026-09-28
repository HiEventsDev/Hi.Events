import {useMutation, useQueryClient} from "@tanstack/react-query";
import {cashlessClient} from "../api/cashless.client.ts";
import {IdParam, UpsertCashlessSalesPointRequest} from "../types.ts";
import {GET_CASHLESS_SALES_POINTS_QUERY_KEY} from "../queries/useGetCashlessSalesPoints.ts";

export const useCreateCashlessSalesPoint = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, salesPointData}: {
            eventId: IdParam,
            salesPointData: UpsertCashlessSalesPointRequest
        }) => cashlessClient.createSalesPoint(eventId, salesPointData),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_CASHLESS_SALES_POINTS_QUERY_KEY]}),
    });
};
