import {api} from "./client";
import {
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    QueryFilters, SortableItem,
    Product,
    ProductPurchase,
    ProductPurchaseExportFilters,
    ProductPurchaseSummary,
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
    purchases: async (eventId: IdParam, productId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<ProductPurchase>>(
            `/events/${eventId}/products/${productId}/purchases` + queryParamsHelper.buildQueryString(pagination)
        );
        return response.data;
    },
    purchaseSummary: async (eventId: IdParam, productId: IdParam, eventOccurrenceId?: IdParam) => {
        const response = await api.get<GenericDataResponse<ProductPurchaseSummary>>(
            `/events/${eventId}/products/${productId}/purchases/summary`,
            {params: eventOccurrenceId ? {event_occurrence_id: eventOccurrenceId} : {}},
        );
        return response.data;
    },
    exportPurchases: async (eventId: IdParam, filters: ProductPurchaseExportFilters): Promise<Blob> => {
        const response = await api.post(`events/${eventId}/products/purchases/export`, filters, {
            responseType: 'blob',
        });
        return new Blob([response.data]);
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

