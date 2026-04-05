import {
    AuthConfigResponse,
    GenericDataResponse,
    LoginResponse,
    OAuthLoginRequest,
} from "../types.ts";
import {api} from './client.ts';

export const authConfigClient = {
    getConfig: async () => {
        const response = await api.get<GenericDataResponse<AuthConfigResponse>>('auth/config');
        return response.data;
    },

    loginWithGoogle: async (data: OAuthLoginRequest) => {
        const response = await api.post<LoginResponse>('auth/google', data);
        return response.data;
    },

    loginWithApple: async (data: OAuthLoginRequest) => {
        const response = await api.post<LoginResponse>('auth/apple', data);
        return response.data;
    },

    validatePrivateEventAccess: async (eventId: number | string, data: { access_code: string }) => {
        const response = await api.post<GenericDataResponse<{ valid: boolean }>>(`public/events/${eventId}/verify-access`, data);
        return response.data;
    },
}
