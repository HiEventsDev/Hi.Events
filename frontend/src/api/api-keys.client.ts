import {api} from "./client.ts";
import {GenericDataResponse, IdParam, CreateApiKeyRequest, RevokeApiKeyRequest, ApiKey} from "../types.ts";

export const apiKeysClient = {
    create: async (request: CreateApiKeyRequest) => {
        const response = await api.post<GenericDataResponse<any>>(`api-keys`, request);
        return response.data;
    },
    all: async () => {
        const response = await api.get<GenericDataResponse<ApiKey[]>>(`api-keys`);
        return response.data;
    },
    revoke: async (tokenId: IdParam) => {
        const response = await api.delete<GenericDataResponse<any>>(`api-keys/${tokenId}`);
        return response.data;
    },
}