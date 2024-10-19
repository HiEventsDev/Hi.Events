import {useMutation} from "@tanstack/react-query";
import {IdParam, SortableItem} from "../types.ts";
import {productClient} from "../api/product.client.ts";

export const useSortProducts = () => {
    return useMutation({
        mutationFn: ({sortedProducts, eventId}: {
            eventId: IdParam,
            sortedProducts: SortableItem[],
        }) => productClient.sortProducts(eventId, sortedProducts)
    });
}