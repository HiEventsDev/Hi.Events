import {api} from "./client.ts";
import {GenericDataResponse, IdParam} from "../types.ts";

export type VatValidationStatus = 'PENDING' | 'VALIDATING' | 'VALID' | 'INVALID' | 'FAILED';

export interface VatSetting {
    id: number;
    organizer_id: number;
    vat_registered: boolean;
    vat_number: string | null;
    vat_validated: boolean;
    vat_validation_status: VatValidationStatus;
    vat_validation_error: string | null;
    vat_validation_attempts: number;
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
    getVatSetting: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<VatSetting>>(
            `organizers/${organizerId}/vat-settings`,
        );
        return response.data;
    },

    upsertVatSetting: async (organizerId: IdParam, data: UpsertVatSettingRequest) => {
        const response = await api.post<GenericDataResponse<VatSetting>>(
            `organizers/${organizerId}/vat-settings`,
            data,
        );
        return response.data;
    },
};
