import {useMutation, useQueryClient} from "@tanstack/react-query";
import {BoxOfficeTenderRequest, IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";
import {GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY} from "../queries/useGetBoxOfficeOrdersPublic.ts";
import {GET_BOX_OFFICE_PRODUCTS_PUBLIC_QUERY_KEY} from "../queries/useGetBoxOfficeProductsPublic.ts";
import {GET_BOX_OFFICE_OCCUPIED_SEATS_QUERY_KEY} from "../queries/useGetBoxOfficeSeating.ts";

export const useTenderBoxOfficeOrder = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({boxOfficeShortId, orderShortId, payload}: {
            boxOfficeShortId: IdParam,
            orderShortId: IdParam,
            payload: BoxOfficeTenderRequest,
        }) => publicBoxOfficeClient.tenderOrder(boxOfficeShortId, orderShortId, payload),

        onSuccess: (_, variables) => {
            void Promise.all([
                queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY, variables.boxOfficeShortId]}),
                queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_PRODUCTS_PUBLIC_QUERY_KEY, variables.boxOfficeShortId]}),
                queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_OCCUPIED_SEATS_QUERY_KEY, variables.boxOfficeShortId]}),
            ]);
        },
    });
}
