import {api} from "./client";
import {publicApi} from "./public-client.ts";
import {
    GenericDataResponse, GenericPaginatedResponse, IdParam, ProductBundle, QueryFilters,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const productBundleClient = {
    create: async (eventId: IdParam, bundle: Partial<ProductBundle>) => {
        const response = await api.post<GenericDataResponse<ProductBundle>>(
            `events/${eventId}/bundles`, bundle
        );
        return response.data;
    },
    update: async (eventId: IdParam, bundleId: IdParam, bundle: Partial<ProductBundle>) => {
        const response = await api.put<GenericDataResponse<ProductBundle>>(
            `events/${eventId}/bundles/${bundleId}`, bundle
        );
        return response.data;
    },
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<ProductBundle>>(
            `events/${eventId}/bundles` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
    delete: async (eventId: IdParam, bundleId: IdParam) => {
        const response = await api.delete<GenericDataResponse<ProductBundle>>(
            `events/${eventId}/bundles/${bundleId}`
        );
        return response.data;
    },
};

export const productBundleClientPublic = {
    all: async (eventId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<ProductBundle[]>>(
            `events/${eventId}/bundles`
        );
        return response.data;
    },
};
