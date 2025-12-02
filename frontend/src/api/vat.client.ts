import {api} from "./client.ts";
import {GenericDataResponse, IdParam} from "../types.ts";

export interface AccountVatSetting {
    id: number;
    account_id: number;
    vat_registered: boolean;
    vat_number: string | null;
    vat_validated: boolean;
    vat_validation_date: string | null;
    business_name: string | null;
    business_address: string | null;
    vat_country_code: string | null;
    created_at: string;
    updated_at: string;
}

export interface UpsertVatSettingRequest {
    vat_registered: boolean;
    vat_number?: string | null;
}

export const vatClient = {
    getVatSetting: async (accountId: IdParam) => {
        const response = await api.get<GenericDataResponse<AccountVatSetting>>(
            `accounts/${accountId}/vat-settings`
        );
        return response.data;
    },

    upsertVatSetting: async (accountId: IdParam, data: UpsertVatSettingRequest) => {
        const response = await api.post<GenericDataResponse<AccountVatSetting>>(
            `accounts/${accountId}/vat-settings`,
            data
        );
        return response.data;
    },
};
