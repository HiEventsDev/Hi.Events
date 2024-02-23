import {useMutation} from "@tanstack/react-query";
import {IdParam, PromoCode} from "../types.ts";
import {promoCodeClient} from "../api/promo-code.client.ts";
import {GET_EVENT_PROMO_CODES_QUERY_KEY} from "../queries/useGetEventPromoCodes.ts";
import {useQueryClient} from "@tanstack/react-query";

export const useCreatePromoCode = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({eventId, promoCodeData}: {
            eventId: IdParam,
            promoCodeData: PromoCode
        }) => promoCodeClient.create(eventId, promoCodeData),
        {
            onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_EVENT_PROMO_CODES_QUERY_KEY]}),
        }
    )
}