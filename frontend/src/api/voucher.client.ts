import {api} from "./client";
import {
    GenericDataResponse, GenericPaginatedResponse, IdParam, PromoCode, QueryFilters,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const voucherClient = {
    create: async (voucher: Partial<PromoCode>) => {
        const response = await api.post<GenericDataResponse<PromoCode>>(
            `vouchers`, voucher
        );
        return response.data;
    },
    all: async (pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<PromoCode>>(
            `vouchers` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
    delete: async (voucherId: IdParam) => {
        const response = await api.delete<GenericDataResponse<PromoCode>>(`vouchers/${voucherId}`);
        return response.data;
    },
};
