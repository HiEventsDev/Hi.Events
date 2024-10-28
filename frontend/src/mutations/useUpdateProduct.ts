import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, Product} from "../types.ts";
import {productClient} from "../api/product.client.ts";
import {GET_PRODUCTS_QUERY_KEY} from "../queries/useGetProducts.ts";
import {GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY} from "../queries/useGetProductCategories.ts";

export const useUpdateProduct = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({productData, productId, eventId}: {
            productData: Product,
            productId: IdParam,
            eventId: IdParam
        }) => productClient.update(eventId, productId, productData),

        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_PRODUCTS_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY]});
        }
    });
}
