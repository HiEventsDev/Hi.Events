import {api} from "./client.ts";
import {
    DisableTwoFactorRequest,
    GenericDataResponse,
    IdParam,
    TwoFactorRecoveryCodes,
    TwoFactorSetup,
    TwoFactorStatus
} from "../types.ts";

export const twoFactorClient = {
    status: async () => {
        const response = await api.get<GenericDataResponse<TwoFactorStatus>>('users/me/two-factor');
        return response.data;
    },
    beginSetup: async (password: string) => {
        const response = await api.post<GenericDataResponse<TwoFactorSetup>>('users/me/two-factor/setup', {password});
        return response.data;
    },
    confirmSetup: async (code: string) => {
        const response = await api.post<GenericDataResponse<TwoFactorRecoveryCodes>>('users/me/two-factor/confirm', {code});
        return response.data;
    },
    regenerateRecoveryCodes: async (code: string) => {
        const response = await api.post<GenericDataResponse<TwoFactorRecoveryCodes>>('users/me/two-factor/recovery-codes', {code});
        return response.data;
    },
    disable: async (request: DisableTwoFactorRequest) => {
        await api.post('users/me/two-factor/disable', request);
    },
    revokeTrustedDevices: async () => {
        await api.delete('users/me/two-factor/trusted-devices');
    },
    revokeTrustedDevice: async (deviceId: IdParam) => {
        await api.delete(`users/me/two-factor/trusted-devices/${deviceId}`);
    },
};
