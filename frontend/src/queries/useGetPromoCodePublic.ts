import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {promoCodeClientPublic} from "../api/promo-code.client.ts";

export const GET_PROMO_CODE_PUBLIC_QUERY_KEY = 'getPromoCodePublic';

export const useGetPromoCodePublic = (eventId: IdParam, promoCode: string|null) => {
    return useQuery({
        queryKey: [GET_PROMO_CODE_PUBLIC_QUERY_KEY, promoCode],

        queryFn: async () => {
           return await promoCodeClientPublic.validateCode(eventId, promoCode);
        },

        enabled: promoCode !== null,
        refetchOnWindowFocus: false,
        retry: 0
    });
};