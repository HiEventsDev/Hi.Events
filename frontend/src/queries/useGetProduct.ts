import {useQuery} from "@tanstack/react-query";
import {IdParam, Product} from "../types.ts";
import {productClient} from "../api/product.client.ts";

export const GET_PRODUCTS_QUERY_KEY = 'getProducts';

export const useGetProduct = (eventId: IdParam, productId: IdParam) => {
    return useQuery<Product>({
        queryKey: [GET_PRODUCTS_QUERY_KEY, eventId, productId],

        queryFn: async () => {
            const {data} = await productClient.findById(eventId, productId);
            return data;
        }
    });
};
