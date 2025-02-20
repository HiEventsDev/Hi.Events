import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {productCategoryClient} from "../api/product-category.client.ts";

export const GET_EVENT_PRODUCT_CATEGORY_QUERY_KEY = 'getEventProductCategory';

export const useGetEventProductCategory = (productCategoryId: IdParam, eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_EVENT_PRODUCT_CATEGORY_QUERY_KEY, eventId, productCategoryId],

        queryFn: async () => {
            const {data} = await productCategoryClient.get(eventId, productCategoryId);
            return data;
        }
    });
};
