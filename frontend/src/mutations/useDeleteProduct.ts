import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {productClient} from "../api/product.client.ts";
import {GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY} from "../queries/useGetProductCategories.ts";

export const useDeleteProduct = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({productId, eventId}: {
            productId: IdParam,
            eventId: IdParam,
        }) => productClient.delete(eventId, productId),

        onSuccess: () => queryClient.invalidateQueries({
            queryKey: [GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY],
        })
    });
}
