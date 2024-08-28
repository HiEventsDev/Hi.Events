import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {promoCodeClient} from "../api/promo-code.client.ts";
import {GET_PROMO_CODE_QUERY_KEY} from "../queries/useGetPromoCode.ts";
import {GET_EVENT_PROMO_CODES_QUERY_KEY} from "../queries/useGetEventPromoCodes.ts";

export const useDeletePromoCode = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({promoCodeId, eventId}: {
            promoCodeId: IdParam,
            eventId: IdParam,
        }) => promoCodeClient.delete(eventId, promoCodeId),

        onSuccess: (_, variables) => {
             queryClient.invalidateQueries({
                 queryKey: [GET_PROMO_CODE_QUERY_KEY, variables.eventId, variables.promoCodeId]
             });
             queryClient.invalidateQueries({
                 queryKey: [GET_EVENT_PROMO_CODES_QUERY_KEY]
             });
        }
    });
}
