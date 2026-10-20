import {api} from "./client.ts";
import {ApiKeysResponse, CreateApiKeyRequest, CreatedApiKey, GenericDataResponse, IdParam} from "../types.ts";

export const apiKeyClient = {
    all: async () => {
        const response = await api.get<ApiKeysResponse>('api-keys');
        return response.data;
    },
    create: async (request: CreateApiKeyRequest) => {
        const response = await api.post<GenericDataResponse<CreatedApiKey>>('api-keys', request);
        return response.data.data;
    },
    delete: async (apiKeyId: IdParam) => {
        await api.delete(`api-keys/${apiKeyId}`);
    },
};
