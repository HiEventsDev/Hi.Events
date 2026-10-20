import {api} from "./client.ts";
import {
    GenericDataResponse,
    IdentityConfirmation,
    IdParam,
    LinkableSsoConnection,
    LoginResponse,
    SsoExchangeResponse,
    SsoIntent,
    SsoProvidersResponse,
    SsoSignInMethods,
    SsoSignupRequest,
    SsoStartResponse,
    UserIdentity,
} from "../types.ts";

const EXCHANGE_TIMEOUT_MS = 20000;

export interface StartSsoRequest {
    intent: Extract<SsoIntent, 'LOGIN' | 'SIGNUP' | 'INVITE'>;
    return_path?: string;
    invitation_token?: string;
    timezone?: string;
    marketing_opt_in?: boolean;
    switch_account?: boolean;
    login_hint?: string;
    silent?: boolean;
}

export interface SsoCodeExchange {
    code: string;
    state: string;
    binding: string;
}

export const ssoClient = {
    providers: async () => {
        const response = await api.get<GenericDataResponse<SsoProvidersResponse>>('auth/sso/providers');
        return response.data.data;
    },

    start: async (provider: string, request: StartSsoRequest) => {
        const response = await api.post<GenericDataResponse<SsoStartResponse>>(`auth/sso/${provider}/start`, request);
        return response.data.data;
    },

    exchange: async (request: SsoCodeExchange) => {
        const response = await api.post<GenericDataResponse<SsoExchangeResponse>>('auth/sso/exchange', request, {timeout: EXCHANGE_TIMEOUT_MS});
        return response.data.data;
    },

    complete: async (request: { login_ticket: string, binding: string, account_id: IdParam }) => {
        const response = await api.post<LoginResponse>('auth/sso/complete', request);
        return response.data;
    },

    sendSignupCode: async (request: { signup_ticket: string, binding: string, locale?: string }) => {
        await api.post('auth/sso/signup/code', request);
    },

    signup: async (request: SsoSignupRequest) => {
        const response = await api.post<LoginResponse>('auth/sso/signup', request);
        return response.data;
    },

    identities: async (): Promise<SsoSignInMethods> => {
        const response = await api.get<GenericDataResponse<UserIdentity[]> & {
            meta?: { linkable_connections?: LinkableSsoConnection[] }
        }>('users/me/sso-identities');

        return {
            identities: response.data.data,
            linkableConnections: response.data.meta?.linkable_connections ?? [],
        };
    },

    startLink: async (provider: string, request: IdentityConfirmation & { return_path?: string }) => {
        const response = await api.post<GenericDataResponse<SsoStartResponse>>(`users/me/sso-identities/${provider}/start`, request);
        return response.data.data;
    },

    startReauth: async (provider: string, returnPath?: string) => {
        const response = await api.post<GenericDataResponse<SsoStartResponse>>(`users/me/sso-identities/${provider}/reauth`, {return_path: returnPath});
        return response.data.data;
    },

    exchangeAuthenticated: async (request: SsoCodeExchange) => {
        const response = await api.post<GenericDataResponse<SsoExchangeResponse>>('users/me/sso-identities/exchange', request, {timeout: EXCHANGE_TIMEOUT_MS});
        return response.data.data;
    },

    deleteIdentity: async (identityId: IdParam, confirmation: IdentityConfirmation) => {
        const response = await api.delete<LoginResponse>(`users/me/sso-identities/${identityId}`, {data: confirmation});
        return response.data;
    },
};
