import {
    GenericDataResponse,
    LoginResponse,
    WebAuthnAuthenticationOptions,
    WebAuthnCredentialInfo,
    WebAuthnRegistrationOptions,
} from "../types.ts";
import {api} from './client.ts';

export const webauthnClient = {
    getRegisterOptions: async () => {
        const response = await api.get<GenericDataResponse<WebAuthnRegistrationOptions>>('auth/webauthn/register-options');
        return response.data;
    },

    register: async (data: { name: string; credential: object }) => {
        const response = await api.post<GenericDataResponse<WebAuthnCredentialInfo>>('auth/webauthn/register', data);
        return response.data;
    },

    getLoginOptions: async (data: { email?: string }) => {
        const response = await api.post<GenericDataResponse<WebAuthnAuthenticationOptions>>('auth/webauthn/login-options', data);
        return response.data;
    },

    login: async (data: { credential: object; account_id?: number }) => {
        const response = await api.post<LoginResponse>('auth/webauthn/login', data);
        return response.data;
    },

    remove: async (credentialId: string) => {
        const response = await api.delete(`auth/webauthn/${credentialId}`);
        return response.data;
    },
}
