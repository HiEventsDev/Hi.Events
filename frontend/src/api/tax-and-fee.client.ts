import {GenericDataResponse, GenericPaginatedResponse, IdParam, TaxAndFee} from "../types.ts";
import {api} from "./client.ts";

export const taxAndFeeClient = {
    create: async (accountId: IdParam, payload: TaxAndFee) => {
        const response = await api.post<GenericDataResponse<TaxAndFee>>(`accounts/${accountId}/taxes-and-fees`, payload);
        return response.data;
    },
    all: async (accountId: IdParam) => {
        const response = await api.get<GenericPaginatedResponse<TaxAndFee>>(`accounts/${accountId}/taxes-and-fees`);
        return response.data;
    },
    delete: async (accountId: IdParam, taxAndFeeId: IdParam) => {
        return await api.delete(`accounts/${accountId}/taxes-and-fees/${taxAndFeeId}`);
    },
    update: async (accountId: IdParam, taxAndFeeId: IdParam, payload: TaxAndFee) => {
        const response = await api.put<GenericDataResponse<TaxAndFee>>(`accounts/${accountId}/taxes-and-fees/${taxAndFeeId}`, payload);
        return response.data;
    }
}