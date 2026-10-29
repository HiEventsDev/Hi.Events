import {AxiosRequestConfig} from "axios";
import {api} from "../../../api/client";
import {publicApi} from "../../../api/public-client";
import {GenericDataResponse, LicenceStatus} from "../../../types";

export interface InstanceInfo {
    white_label: boolean;
    dev_mode: boolean;
}

export type LicencePlan = 'own_events' | 'platform' | 'cloud';

export interface AdminLicence {
    status: LicenceStatus;
    features: string[];
    lid: string | null;
    customer: string | null;
    plan: LicencePlan | null;
    issued_at: string | null;
    expires_at: string | null;
    grace_ends_at: string | null;
    covered_sales: number | null;
    invalid_reason: string | null;
}

export interface LicenceSalesPeriod {
    starts_on: string;
    ends_on: string;
    is_current: boolean;
    sales: { currency: string, amount: number }[];
}

export const licenceClientPublic = {
    getInstanceInfo: async (config?: AxiosRequestConfig) => {
        const response = await publicApi.get<GenericDataResponse<InstanceInfo>>('/instance', config);
        return response.data;
    },
};

export const licenceAdminClient = {
    getLicence: async () => {
        const response = await api.get<GenericDataResponse<AdminLicence>>('admin/licence');
        return response.data;
    },
    getLicenceSales: async () => {
        const response = await api.get<GenericDataResponse<LicenceSalesPeriod[]>>('admin/licence/sales');
        return response.data;
    },
};
