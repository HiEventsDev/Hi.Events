import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {productCategoryClient} from "../api/product-category.client.ts";
import {GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY} from "../queries/useGetProductCategories.ts";

export const useDeleteProductCategory = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({productCategoryId, eventId}: {
            productCategoryId: IdParam,
            eventId: IdParam,
        }) => productCategoryClient.delete(eventId, productCategoryId),

        onSuccess: () => queryClient.invalidateQueries({
            queryKey: [GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY],
        })
    });
};
