import {api} from "./client";
import {publicApi} from "./public-client";
import {
    BulkUpdateOccurrencesRequest,
    EventOccurrence,
    GenerateOccurrencesRequest,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    OccurrenceGenerationStatus,
    ProductOccurrenceVisibility,
    ProductPriceOccurrenceOverride,
    QueryFilters,
    UpsertEventOccurrenceRequest,
    UpsertPriceOverrideRequest,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const eventOccurrenceClient = {
    all: async (eventId: IdParam, pagination: QueryFilters, options: {includeStats?: boolean} = {}) => {
        const queryString = queryParamsHelper.buildQueryString(pagination);
        const separator = queryString.includes('?') ? '&' : '?';
        const url = `events/${eventId}/occurrences` + queryString
            + (options.includeStats === false ? `${separator}include_stats=false` : '');
        const response = await api.get<GenericPaginatedResponse<EventOccurrence>>(url);
        return response.data;
    },

    get: async (eventId: IdParam, occurrenceId: IdParam) => {
        const response = await api.get<GenericDataResponse<EventOccurrence>>(
            `events/${eventId}/occurrences/${occurrenceId}`
        );
        return response.data;
    },

    create: async (eventId: IdParam, data: UpsertEventOccurrenceRequest) => {
        const response = await api.post<GenericDataResponse<EventOccurrence>>(
            `events/${eventId}/occurrences`,
            data
        );
        return response.data;
    },

    update: async (eventId: IdParam, occurrenceId: IdParam, data: UpsertEventOccurrenceRequest) => {
        const response = await api.put<GenericDataResponse<EventOccurrence>>(
            `events/${eventId}/occurrences/${occurrenceId}`,
            data
        );
        return response.data;
    },

    delete: async (eventId: IdParam, occurrenceId: IdParam) => {
        const response = await api.delete<GenericDataResponse<EventOccurrence>>(
            `events/${eventId}/occurrences/${occurrenceId}`
        );
        return response.data;
    },

    cancel: async (eventId: IdParam, occurrenceId: IdParam, refundOrders: boolean = false) => {
        const response = await api.post<GenericDataResponse<EventOccurrence>>(
            `events/${eventId}/occurrences/${occurrenceId}/cancel`,
            {refund_orders: refundOrders}
        );
        return response.data;
    },

    reactivate: async (eventId: IdParam, occurrenceId: IdParam) => {
        const response = await api.post<GenericDataResponse<EventOccurrence>>(
            `events/${eventId}/occurrences/${occurrenceId}/reactivate`,
            {}
        );
        return response.data;
    },

    generate: async (eventId: IdParam, data: GenerateOccurrencesRequest) => {
        const response = await api.post<OccurrenceGenerationStatus>(
            `events/${eventId}/occurrences/generate`,
            data
        );
        return response.data;
    },

    getGenerationStatus: async (eventId: IdParam, jobUuid: string) => {
        const response = await api.get<OccurrenceGenerationStatus>(
            `events/${eventId}/occurrences/generate/status?job_uuid=${jobUuid}`
        );
        return response.data;
    },

    bulkUpdate: async (eventId: IdParam, data: BulkUpdateOccurrencesRequest) => {
        const response = await api.post<{ updated_count: number; updated_ids: number[] }>(
            `events/${eventId}/occurrences/bulk-update`,
            data
        );
        return response.data;
    },

    getPriceOverrides: async (eventId: IdParam, occurrenceId: IdParam) => {
        const response = await api.get<GenericDataResponse<ProductPriceOccurrenceOverride[]>>(
            `events/${eventId}/occurrences/${occurrenceId}/price-overrides`
        );
        return response.data;
    },

    upsertPriceOverride: async (eventId: IdParam, occurrenceId: IdParam, data: UpsertPriceOverrideRequest) => {
        const response = await api.put<GenericDataResponse<ProductPriceOccurrenceOverride>>(
            `events/${eventId}/occurrences/${occurrenceId}/price-overrides`,
            data
        );
        return response.data;
    },

    deletePriceOverride: async (eventId: IdParam, occurrenceId: IdParam, overrideId: IdParam) => {
        const response = await api.delete<GenericDataResponse<ProductPriceOccurrenceOverride>>(
            `events/${eventId}/occurrences/${occurrenceId}/price-overrides/${overrideId}`
        );
        return response.data;
    },

    getProductVisibility: async (eventId: IdParam, occurrenceId: IdParam) => {
        const response = await api.get<GenericDataResponse<ProductOccurrenceVisibility[]>>(
            `events/${eventId}/occurrences/${occurrenceId}/product-visibility`
        );
        return response.data;
    },

    updateProductVisibility: async (eventId: IdParam, occurrenceId: IdParam, productIds: IdParam[]) => {
        const response = await api.put<GenericDataResponse<any>>(
            `events/${eventId}/occurrences/${occurrenceId}/product-visibility`,
            {product_ids: productIds}
        );
        return response.data;
    },
};

export const eventOccurrenceClientPublic = {
    all: async (eventId: IdParam, startDateFrom: string, startDateTo: string) => {
        const params = new URLSearchParams({
            start_date_from: startDateFrom,
            start_date_to: startDateTo,
        });
        const response = await publicApi.get<GenericDataResponse<EventOccurrence[]>>(
            `events/${eventId}/occurrences?${params.toString()}`
        );
        return response.data;
    },
};
