import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, ProductCategory} from "../types.ts";
import {GET_EVENT_PRODUCT_CATEGORY_QUERY_KEY} from "../queries/useGetProductCategory.ts";
import {GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY} from "../queries/useGetProductCategories.ts";
import {productCategoryClient} from "../api/product-category.client.ts";

export const useEditProductCategory = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({productCategoryData, eventId, productCategoryId}: {
            eventId: IdParam,
            productCategoryData: ProductCategory,
            productCategoryId: IdParam,
        }) => productCategoryClient.update(eventId, productCategoryId, productCategoryData),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_PRODUCT_CATEGORY_QUERY_KEY, variables.eventId, variables.productCategoryId]
            });
            return queryClient.invalidateQueries({queryKey: [GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY]});
        }
    });
};
