import {useMutation, useQueryClient} from "@tanstack/react-query";
import {BoxOfficeOrderRequest, IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";
import {GET_BOX_OFFICE_PRODUCTS_PUBLIC_QUERY_KEY} from "../queries/useGetBoxOfficeProductsPublic.ts";
import {GET_BOX_OFFICE_OCCUPIED_SEATS_QUERY_KEY} from "../queries/useGetBoxOfficeSeating.ts";

export const useCreateBoxOfficeOrder = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({boxOfficeShortId, payload}: {
            boxOfficeShortId: IdParam,
            payload: BoxOfficeOrderRequest,
        }) => publicBoxOfficeClient.createOrder(boxOfficeShortId, payload),

        onSettled: (_, __, variables) => {
            void Promise.all([
                queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_PRODUCTS_PUBLIC_QUERY_KEY, variables.boxOfficeShortId]}),
                queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_OCCUPIED_SEATS_QUERY_KEY, variables.boxOfficeShortId]}),
            ]);
        },
    });
}
