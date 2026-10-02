import {AxiosRequestConfig} from "axios";
import {api} from "../../../api/client";
import {publicApi} from "../../../api/public-client";
import {GenericDataResponse, LicenceStatus} from "../../../types";

export interface InstanceInfo {
    white_label: boolean;
    dev_mode: boolean;
}

export interface AdminLicence {
    status: LicenceStatus;
    features: string[];
    lid: string | null;
    customer: string | null;
    plan: string | null;
    issued_at: string | null;
    expires_at: string | null;
    grace_ends_at: string | null;
    invalid_reason: string | null;
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
};
