import {publicApi} from "./public-client.ts";
import {GenericDataResponse, IdParam, Product} from "../types";

export const upsellClientPublic = {
    getUpsells: async (eventId: IdParam, productIds: number[]) => {
        const params = productIds.length > 0 ? `?product_ids=${productIds.join(',')}` : '';
        const response = await publicApi.get<GenericDataResponse<Product[]>>(
            `events/${eventId}/products/upsells${params}`
        );
        return response.data;
    },
};
