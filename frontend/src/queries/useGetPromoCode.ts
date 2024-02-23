import {useQuery} from "@tanstack/react-query";
import {IdParam, PromoCode} from "../types.ts";
import {promoCodeClient} from "../api/promo-code.client.ts";

export const GET_PROMO_CODE_QUERY_KEY = 'getPromoCode';

export const useGetPromoCode = (eventId: IdParam, promoCodeId: IdParam) => {
    return useQuery<PromoCode, Error>(
        [GET_PROMO_CODE_QUERY_KEY, eventId, promoCodeId],
        async () => {
            const {data} = await promoCodeClient.findById(eventId, promoCodeId);
            return data;
        },
    )
};