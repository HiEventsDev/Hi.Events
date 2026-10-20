import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";
import {GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY} from "../queries/useGetBoxOfficeOrdersPublic.ts";
import {GET_BOX_OFFICE_PRODUCTS_PUBLIC_QUERY_KEY} from "../queries/useGetBoxOfficeProductsPublic.ts";
import {GET_BOX_OFFICE_OCCUPIED_SEATS_QUERY_KEY} from "../queries/useGetBoxOfficeSeating.ts";

export const useStartBoxOfficeCardPayment = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({boxOfficeShortId, orderShortId}: {
            boxOfficeShortId: IdParam,
            orderShortId: IdParam,
        }) => publicBoxOfficeClient.startCardPayment(boxOfficeShortId, orderShortId),

        onSettled: (_, __, variables) => {
            void Promise.all([
                queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY, variables.boxOfficeShortId]}),
                queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_PRODUCTS_PUBLIC_QUERY_KEY, variables.boxOfficeShortId]}),
                queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_OCCUPIED_SEATS_QUERY_KEY, variables.boxOfficeShortId]}),
            ]);
        },
    });
}
