import {api} from "./client";
import {
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    QueryFilters, SortableItem,
    Product,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";
import {publicApi} from "./public-client.ts";

export const productClient = {
    findById: async (eventId: IdParam, productId: IdParam) => {
        const response = await api.get<GenericDataResponse<Product>>(`/events/${eventId}/products/${productId}`);
        return response.data;
    },
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<Product>>(
            `/events/${eventId}/products` + queryParamsHelper.buildQueryString(pagination)
        );
        return response.data;
    },
    create: async (eventId: IdParam, product: Product) => {
        const response = await api.post<GenericDataResponse<Product>>(`events/${eventId}/products`, product);
        return response.data;
    },
    update: async (eventId: IdParam, productId: IdParam, product: Product) => {
        const response = await api.put<GenericDataResponse<Product>>(`events/${eventId}/products/${productId}`, product);
        return response.data;
    },
    delete: async (eventId: IdParam, productId: IdParam) => {
        const response = await api.delete<GenericDataResponse<Product>>(`/events/${eventId}/products/${productId}`);
        return response.data;
    },
    sortAllProducts: async (eventId: IdParam, sortedCategories: { product_category_id: IdParam, sorted_products: SortableItem[] }[]) => {
        return await api.post(`/events/${eventId}/products/sort`, {
            'sorted_categories': sortedCategories,
        });
    }
}

export const productClientPublic = {
    findByEventId: async (eventId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<Product>>(`/events/${eventId}/products`);
        return response.data;
    },
}

