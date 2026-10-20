import {api} from "../../../api/client.ts";
import {
    AccountSso,
    AccountSsoConnection,
    AccountSsoConnectionRequest,
    AccountSsoDomain,
    GenericDataResponse,
    IdParam,
    SsoEnforcementPreview,
    SsoProvider,
    SsoStartResponse,
} from "../../../types.ts";

const base = (accountId: IdParam) => `accounts/${accountId}/sso`;

export const accountSsoClient = {
    discover: async (email: string) => {
        const response = await api.post<GenericDataResponse<SsoProvider>>('auth/sso/discover', {email});
        return response.data.data;
    },

    get: async (accountId: IdParam) => {
        const response = await api.get<GenericDataResponse<AccountSso>>(base(accountId));
        return response.data.data;
    },

    createConnection: async (accountId: IdParam, request: AccountSsoConnectionRequest) => {
        const response = await api.post<GenericDataResponse<AccountSsoConnection>>(`${base(accountId)}/connection`, request);
        return response.data.data;
    },

    updateConnection: async (accountId: IdParam, request: AccountSsoConnectionRequest) => {
        const response = await api.put<GenericDataResponse<AccountSsoConnection>>(`${base(accountId)}/connection`, request);
        return response.data.data;
    },

    checkConnection: async (accountId: IdParam, request: AccountSsoConnectionRequest) => {
        await api.post(`${base(accountId)}/connection/check`, request);
    },

    setConnectionStatus: async (accountId: IdParam, status: AccountSsoConnection['status']) => {
        const response = await api.put<GenericDataResponse<AccountSsoConnection>>(`${base(accountId)}/connection/status`, {status});
        return response.data.data;
    },

    deleteConnection: async (accountId: IdParam) => {
        await api.delete(`${base(accountId)}/connection`);
    },

    startTest: async (accountId: IdParam, returnPath: string) => {
        const response = await api.post<GenericDataResponse<SsoStartResponse>>(`${base(accountId)}/connection/test`, {return_path: returnPath});
        return response.data.data;
    },

    addDomain: async (accountId: IdParam, domain: string) => {
        const response = await api.post<GenericDataResponse<AccountSsoDomain>>(`${base(accountId)}/domains`, {domain});
        return response.data.data;
    },

    verifyDomain: async (accountId: IdParam, domainId: IdParam) => {
        const response = await api.post<GenericDataResponse<AccountSsoDomain>>(`${base(accountId)}/domains/${domainId}/verify`);
        return response.data.data;
    },

    deleteDomain: async (accountId: IdParam, domainId: IdParam) => {
        await api.delete(`${base(accountId)}/domains/${domainId}`);
    },

    enforcementPreview: async (accountId: IdParam) => {
        const response = await api.get<GenericDataResponse<SsoEnforcementPreview>>(`${base(accountId)}/enforcement`);
        return response.data.data;
    },

    updateEnforcement: async (accountId: IdParam, enabled: boolean, exemptAccountUserIds: IdParam[] = []) => {
        const response = await api.put<GenericDataResponse<AccountSsoConnection>>(`${base(accountId)}/enforcement`, {
            enabled,
            exempt_account_user_ids: exemptAccountUserIds,
        });
        return response.data.data;
    },

    setMemberEnforced: async (accountId: IdParam, accountUserId: IdParam, ssoEnforced: boolean) => {
        const response = await api.put<GenericDataResponse<SsoEnforcementPreview>>(`${base(accountId)}/members/${accountUserId}`, {sso_enforced: ssoEnforced});
        return response.data.data;
    },
};
