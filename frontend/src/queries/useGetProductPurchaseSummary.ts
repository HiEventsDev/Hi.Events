import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {productClient} from "../api/product.client.ts";

export const GET_PRODUCT_PURCHASE_SUMMARY_QUERY_KEY = 'getProductPurchaseSummary';

export const useGetProductPurchaseSummary = (eventId: IdParam, productId: IdParam, eventOccurrenceId?: IdParam) => {
    return useQuery({
        queryKey: [GET_PRODUCT_PURCHASE_SUMMARY_QUERY_KEY, eventId, productId, eventOccurrenceId ?? null],
        queryFn: async () => {
            const {data} = await productClient.purchaseSummary(eventId, productId, eventOccurrenceId);
            return data;
        },
    });
};
