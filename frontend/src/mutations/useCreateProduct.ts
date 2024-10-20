import {IdParam, Product} from "../types.ts";
import {productClient} from "../api/product.client.ts";
import {queryClient} from "../utilites/queryClient.ts";
import {GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY} from "../queries/useGetProductCategories.ts";
import {useMutation} from "@tanstack/react-query";

export const useCreateProduct = () => {
    return useMutation({
        mutationFn: ({productData, eventId}: {
            eventId: IdParam,
            productData: Product,
        }) => productClient.create(eventId, productData),

        onSuccess: (_, variables) => {
            return queryClient
                .invalidateQueries({queryKey: [GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY, variables.eventId]})
        }
    });
}
