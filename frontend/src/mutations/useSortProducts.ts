import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, SortableItem} from "../types.ts";
import {productClient} from "../api/product.client.ts";
import {GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY} from "../queries/useGetProductCategories.ts";

export const useSortProducts = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, sortedCategories}: {
            eventId: IdParam,
            sortedCategories: { product_category_id: IdParam, sorted_products: SortableItem[] }[],
        }) => productClient.sortAllProducts(eventId, sortedCategories),
        onSuccess: () => {
            return queryClient.invalidateQueries({queryKey: [GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY]});
        }
    });
}
