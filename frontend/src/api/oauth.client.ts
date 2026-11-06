import {api} from "./client.ts";
import {ApproveOAuthRequest, GenericDataResponse, IdParam, OAuthConnection, OAuthConsent, OAuthRedirect} from "../types.ts";

const requestPath = (requestId: string) => `oauth/authorization-requests/${encodeURIComponent(requestId)}`;

export const oauthClient = {
    authorizationRequest: async (requestId: string) => {
        const response = await api.get<GenericDataResponse<OAuthConsent>>(requestPath(requestId));
        return response.data.data;
    },
    approve: async (requestId: string, request: ApproveOAuthRequest) => {
        const response = await api.post<GenericDataResponse<OAuthRedirect>>(`${requestPath(requestId)}/approve`, request);
        return response.data.data;
    },
    deny: async (requestId: string) => {
        const response = await api.post<GenericDataResponse<OAuthRedirect>>(`${requestPath(requestId)}/deny`);
        return response.data.data;
    },
    connections: async () => {
        const response = await api.get<GenericDataResponse<OAuthConnection[]>>('oauth/connections');
        return response.data.data;
    },
    disconnect: async (connectionId: IdParam) => {
        await api.delete(`oauth/connections/${connectionId}`);
    },
};
