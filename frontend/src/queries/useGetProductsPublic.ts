import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, QueryFilters, Product} from "../types.ts";
import {productClient} from "../api/product.client.ts";

export const GET_PUBLIC_PRODUCTS_QUERY_KEY = 'getProductsPublic';

export const useGetProducts = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<Product>>({
            queryKey: [GET_PUBLIC_PRODUCTS_QUERY_KEY, eventId, pagination],
            queryFn: async () => await productClient.all(eventId, pagination),
        }
    )
};