import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {productCategoryClient} from "../api/product-category.client.ts";

export const GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY = 'getEventProductCategories';

export const useGetEventProductCategories = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_EVENT_PRODUCT_CATEGORIES_QUERY_KEY, eventId],

        queryFn: async () => {
            return await productCategoryClient.all(eventId);
        },
    });
};
