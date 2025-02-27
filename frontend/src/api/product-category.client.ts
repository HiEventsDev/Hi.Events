import { api } from "./client";
import {
    ProductCategory,
    GenericDataResponse,
    IdParam,
} from "../types";

export const productCategoryClient = {
    create: async (eventId: IdParam, productCategory: ProductCategory) => {
        const response = await api.post<GenericDataResponse<ProductCategory>>(
            `events/${eventId}/product-categories`,
            productCategory
        );
        return response.data;
    },

    update: async (eventId: IdParam, productCategoryId: IdParam, productCategory: ProductCategory) => {
        const response = await api.put<GenericDataResponse<ProductCategory>>(
            `events/${eventId}/product-categories/${productCategoryId}`,
            productCategory
        );
        return response.data;
    },

    all: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<ProductCategory[]>>(
            `events/${eventId}/product-categories`
        );
        return response.data;
    },

    get: async (eventId: IdParam, productCategoryId: IdParam) => {
        const response = await api.get<GenericDataResponse<ProductCategory>>(
            `events/${eventId}/product-categories/${productCategoryId}`
        );
        return response.data;
    },

    delete: async (eventId: IdParam, productCategoryId: IdParam) => {
        const response = await api.delete<GenericDataResponse<ProductCategory>>(
            `events/${eventId}/product-categories/${productCategoryId}`
        );
        return response.data;
    },
};
