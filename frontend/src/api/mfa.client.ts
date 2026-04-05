import {
    GenericDataResponse,
    LoginResponse,
    MfaSetupResponse,
    MfaStatusResponse,
    MfaVerifyRequest,
} from "../types.ts";
import {api} from './client.ts';

export const mfaClient = {
    setup: async () => {
        const response = await api.get<GenericDataResponse<MfaSetupResponse>>('auth/mfa/setup');
        return response.data;
    },

    confirm: async (data: { code: string; secret: string }) => {
        const response = await api.post<GenericDataResponse<{ recovery_codes: string[] }>>('auth/mfa/confirm', data);
        return response.data;
    },

    disable: async (data: { password: string }) => {
        const response = await api.post('auth/mfa/disable', data);
        return response.data;
    },

    verify: async (data: MfaVerifyRequest) => {
        const response = await api.post<LoginResponse>('auth/mfa/verify', data);
        return response.data;
    },

    regenerateRecoveryCodes: async (data: { password: string }) => {
        const response = await api.post<GenericDataResponse<{ recovery_codes: string[] }>>('auth/mfa/recovery-codes', data);
        return response.data;
    },

    getStatus: async () => {
        const response = await api.get<GenericDataResponse<MfaStatusResponse>>('auth/mfa/status');
        return response.data;
    },
}
