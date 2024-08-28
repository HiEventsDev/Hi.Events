import {useMutation} from "@tanstack/react-query";
import {IdParam, PromoCode} from "../types.ts";
import {promoCodeClient} from "../api/promo-code.client.ts";
import {GET_EVENT_PROMO_CODES_QUERY_KEY} from "../queries/useGetEventPromoCodes.ts";
import {useQueryClient} from "@tanstack/react-query";
import {GET_PROMO_CODE_QUERY_KEY} from "../queries/useGetPromoCode.ts";

export const useUpdatePromoCode = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, promoCodeId, promoCodeData}: {
            promoCodeId: IdParam,
            eventId: IdParam,
            promoCodeData: PromoCode
        }) => promoCodeClient.update(eventId, promoCodeId, promoCodeData),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_EVENT_PROMO_CODES_QUERY_KEY]})
            queryClient.invalidateQueries({queryKey: [GET_PROMO_CODE_QUERY_KEY]})
        }
    });
}