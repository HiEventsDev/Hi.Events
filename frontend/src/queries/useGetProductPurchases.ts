import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {productClient} from "../api/product.client.ts";

export const GET_PRODUCT_PURCHASES_QUERY_KEY = 'getProductPurchases';

export const useGetProductPurchases = (eventId: IdParam, productId: IdParam, filters: QueryFilters) => {
    return useQuery({
        queryKey: [GET_PRODUCT_PURCHASES_QUERY_KEY, eventId, productId, filters],
        queryFn: () => productClient.purchases(eventId, productId, filters),
        placeholderData: keepPreviousData,
    });
};
