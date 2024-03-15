import {api} from "./client";
import {
    GenericDataResponse, GenericPaginatedResponse, IdParam, PromoCode, QueryFilters,
} from "../types";
import {publicApi} from "./public-client.ts";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const promoCodeClient = {
    create: async (eventId: IdParam, promoCode: PromoCode) => {
        const response = await api.post<GenericDataResponse<PromoCode>>(
            `events/${eventId}/promo-codes`, promoCode
        );
        return response.data;
    },
    update: async (eventId: IdParam, promoCodeId: IdParam, promoCode: PromoCode) => {
        const response = await api.put<GenericDataResponse<PromoCode>>(
            `events/${eventId}/promo-codes/${promoCodeId}`, promoCode
        );
        return response.data;
    },
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<PromoCode>>(
            `events/${eventId}/promo-codes` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
    findById: async (eventId: IdParam, promoCodeId: IdParam) => {
        const response = await api.get<GenericDataResponse<PromoCode>>(`events/${eventId}/promo-codes/${promoCodeId}`);
        return response.data;
    },
    delete: async (eventId: IdParam, promoCodeId: IdParam) => {
        const response = await api.delete<GenericDataResponse<PromoCode>>(`events/${eventId}/promo-codes/${promoCodeId}`);
        return response.data;
    },
}

export const promoCodeClientPublic = {
    validateCode: async (eventId: IdParam, promoCode: string | null) => {
        const response = await publicApi.get<{ valid: boolean }>(
            `events/${eventId}/promo-codes/${promoCode}`
        );
        return response.data;
    },
}
