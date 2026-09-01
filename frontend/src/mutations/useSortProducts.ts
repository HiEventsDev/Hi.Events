import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, SortableItem} from "../types.ts";
import {productClient} from "../api/product.client.ts";
import {GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY} from "../queries/useGetProductCategories.ts";
import {GET_EVENT_QUERY_KEY} from "../queries/useGetEvent.ts";

export const useSortProducts = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, sortedCategories}: {
            eventId: IdParam,
            sortedCategories: { product_category_id: IdParam, sorted_products: SortableItem[] }[],
        }) => productClient.sortAllProducts(eventId, sortedCategories),
        onSuccess: () => {
            return Promise.all([
                queryClient.invalidateQueries({queryKey: [GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY]}),
                queryClient.invalidateQueries({queryKey: [GET_EVENT_QUERY_KEY]}),
            ]);
        }
    });
}
