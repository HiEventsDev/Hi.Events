import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, ProductCategory} from "../types.ts";
import {GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY} from "../queries/useGetProductCategories.ts";
import {productCategoryClient} from "../api/product-category.client.ts";

export const useCreateProductCategory = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({productCategoryData, eventId}: {
            eventId: IdParam,
            productCategoryData: ProductCategory,
        }) => productCategoryClient.create(eventId, productCategoryData),

        onSuccess: (_, variables) => {
            return queryClient
                .invalidateQueries({queryKey: [GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY, variables.eventId]});
        }
    });
};
