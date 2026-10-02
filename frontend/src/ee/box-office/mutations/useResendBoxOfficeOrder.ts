import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";
import {GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY} from "../queries/useGetBoxOfficeOrdersPublic.ts";

export const useResendBoxOfficeOrder = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({boxOfficeShortId, orderShortId, email}: {
            boxOfficeShortId: IdParam,
            orderShortId: IdParam,
            email?: string,
        }) => publicBoxOfficeClient.resendConfirmation(boxOfficeShortId, orderShortId, email),

        onSuccess: (_, variables) => queryClient.invalidateQueries({
            queryKey: [GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY, variables.boxOfficeShortId],
        }),
    });
}
