import {useQuery} from "@tanstack/react-query";
import {promoCodeClient} from "../api/promo-code.client.ts";
import {GenericPaginatedResponse, IdParam, PromoCode, QueryFilters} from "../types.ts";

export const GET_EVENT_PROMO_CODES_QUERY_KEY = 'getEventPromoCodes';

export const useGetEventPromoCodes = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<PromoCode>>({
            queryKey: [GET_EVENT_PROMO_CODES_QUERY_KEY, eventId, pagination],
            queryFn: async () => await promoCodeClient.all(eventId, pagination),
        }
    )
};
